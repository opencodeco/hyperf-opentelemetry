<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Transport;

use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\ErrorFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Http2\Client;
use Swoole\Http2\Request;
use Throwable;

final class SwooleGrpcTransport implements TransportInterface
{
    private bool $closed = false;

    private ?Client $client = null;

    private ?Channel $mutex = null;

    /**
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $method,
        private readonly array $headers = [],
        private readonly float $timeout = 10.0,
        private readonly bool $ssl = false,
        private readonly ?string $compression = null,
        private readonly int $retryDelay = 100,
        private readonly int $maxRetries = 3,
    ) {
    }

    public function contentType(): string
    {
        return ContentTypes::PROTOBUF;
    }

    public function send(string $payload, ?CancellationInterface $cancellation = null): FutureInterface
    {
        if ($this->closed) {
            return new ErrorFuture(new RuntimeException('Transport is closed'));
        }

        $mutex = $this->getMutex();
        if ($mutex->pop() === false) {
            return new ErrorFuture(new RuntimeException('Transport is closed'));
        }

        try {
            if ($this->closed) {
                return new ErrorFuture(new RuntimeException('Transport is closed'));
            }

            return $this->attemptSend($payload);
        } catch (Throwable $e) {
            return new ErrorFuture($e);
        } finally {
            $mutex->push(true); // release — returns false silently if channel was closed by shutdown
        }
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        if ($this->closed) {
            return false;
        }

        $this->closed = true;
        $this->mutex?->close(); // unblock any coroutines waiting to acquire the mutex

        if ($this->client !== null) {
            $this->client->close();
            $this->client = null;
        }

        return true;
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return ! $this->closed;
    }

    /**
     * @SuppressWarnings(PHPMD.ErrorControlOperator)
     */
    private function attemptSend(string $payload): FutureInterface
    {
        $data = $this->compress($payload);
        $lastError = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; ++$attempt) {
            if ($this->closed) {
                return new ErrorFuture(new RuntimeException('Transport is closed'));
            }

            if ($attempt > 0 && $this->retryDelay > 0) {
                Coroutine::sleep($this->retryDelay / 1000.0);
            }

            try {
                return $this->doRequest($this->getClient(), $data);
            } catch (Throwable $e) {
                $this->resetClient();
                $lastError = $e;
            }
        }

        return new ErrorFuture($lastError ?? new RuntimeException('Unknown transport error after retries'));
    }

    /**
     * Executes a single gRPC send+recv cycle.
     *
     * Throws RuntimeException on send failure so the caller can reset the client and retry.
     * Returns ErrorFuture on recv failure (delivery uncertain — no retry).
     *
     * Note: @$client->recv() suppresses E_DEPRECATED from Swoole setting $serverLastStreamId
     * as a dynamic property in PHP 8.2+. Hyperf's ErrorExceptionHandler would otherwise convert
     * the deprecation notice into an ErrorException, aborting the recv() call.
     *
     * @throws RuntimeException on retryable send failure
     * @SuppressWarnings(PHPMD.ErrorControlOperator)
     */
    private function doRequest(Client $client, string $data): FutureInterface
    {
        $request = new Request();
        $request->method = 'POST';
        $request->path = $this->method;
        $request->headers = $this->buildHeaders();
        $request->data = $this->packMessage($data);

        $streamId = $client->send($request);

        if ($streamId === false || $streamId <= 0) {
            // Throw so the retry loop can reset the client and try again;
            // data was never transmitted so retrying is safe.
            throw new RuntimeException(
                'Failed to send gRPC request: ' . ($client->errMsg ?: 'unknown error')
            );
        }

        $response = @$client->recv($this->timeout);

        if ($response === false) {
            $this->resetClient($client);
            // Do not retry: send succeeded, so delivery is uncertain — retrying could duplicate exports.
            return new ErrorFuture(new RuntimeException(
                'Failed to receive gRPC response: ' . ($client->errMsg ?: 'timeout')
            ));
        }

        $grpcStatus = (int) ($response->headers['grpc-status'] ?? 0);
        if ($grpcStatus !== 0) {
            $grpcMessage = $response->headers['grpc-message'] ?? 'Unknown error';
            // gRPC application error — not retried
            return new ErrorFuture(new RuntimeException("gRPC error: {$grpcMessage}", $grpcStatus));
        }

        return new CompletedFuture(null);
    }

    private function getMutex(): Channel
    {
        if ($this->mutex === null) {
            $this->mutex = new Channel(1);
            $this->mutex->push(true); // initially unlocked
        }

        return $this->mutex;
    }

    private function resetClient(?Client $client = null): void
    {
        $target = $client ?? $this->client;
        $target?->close();
        if ($target === $this->client) {
            $this->client = null;
        }
    }

    private function getClient(): Client
    {
        if ($this->client !== null && ! $this->client->connected) {
            $this->client->close();
            $this->client = null;
        }

        if ($this->client === null) {
            $client = new Client($this->host, $this->port, $this->ssl);
            $client->set([
                'timeout' => $this->timeout,
            ]);

            if (! $client->connect()) {
                $errMsg = $client->errMsg;
                $client->close();
                throw new RuntimeException(
                    "Failed to connect to {$this->host}:{$this->port}: " . $errMsg
                );
            }

            $this->client = $client;
        }

        return $this->client;
    }

    private function compress(string $payload): string
    {
        if ($this->compression === TransportFactoryInterface::COMPRESSION_GZIP) {
            $compressed = gzencode($payload, 6);
            if ($compressed === false) {
                throw new RuntimeException('Failed to compress payload with gzip');
            }
            return $compressed;
        }

        if ($this->compression === TransportFactoryInterface::COMPRESSION_DEFLATE) {
            $compressed = gzdeflate($payload, 6);
            if ($compressed === false) {
                throw new RuntimeException('Failed to compress payload with deflate');
            }
            return $compressed;
        }

        return $payload;
    }

    private function buildHeaders(): array
    {
        $headers = array_merge([
            'content-type' => 'application/grpc',
            'te' => 'trailers',
        ], $this->headers);

        if ($this->compression === TransportFactoryInterface::COMPRESSION_GZIP) {
            $headers['grpc-encoding'] = 'gzip';
        } elseif ($this->compression === TransportFactoryInterface::COMPRESSION_DEFLATE) {
            $headers['grpc-encoding'] = 'deflate';
        }

        return $headers;
    }

    private function packMessage(string $data): string
    {
        $compressed = $this->compression !== null ? 1 : 0;
        // gRPC message frame: [1-byte compressed flag][4-byte big-endian message length][message]
        return pack('CN', $compressed, strlen($data)) . $data;
    }
}
