<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Transport;

use Closure;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\ErrorFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use RuntimeException;
use Swoole\Coroutine\Http2\Client;
use Swoole\Http2\Request;
use Throwable;

final class SwooleGrpcTransport implements TransportInterface
{
    private bool $closed = false;

    private bool $requestSent = false;

    private ?Client $client = null;

    /**
     * @param null|Closure(string, int, bool, float): Client $clientFactory
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
        private readonly ?Closure $clientFactory = null,
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

        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                return $this->executeRequest($payload);
            } catch (Throwable $e) {
                $this->resetClient();
                if ($attempt === 0 && $this->isRetryable($e)) {
                    continue;
                }

                return new ErrorFuture($e);
            }
        }

        return new ErrorFuture(new RuntimeException('Failed to send gRPC request'));
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        if ($this->closed) {
            return false;
        }

        $this->closed = true;

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

    private function executeRequest(string $payload): FutureInterface
    {
        $this->requestSent = false;
        $client = $this->getClient();
        $data = $this->compress($payload);

        $request = new Request();
        $request->method = 'POST';
        $request->path = $this->method;
        $request->headers = $this->buildHeaders();
        $request->data = $this->packMessage($data);

        $timeout = $this->timeout;
        $streamId = $this->callClient(static fn () => $client->send($request));
        if ($streamId === false || $streamId <= 0) {
            throw new RuntimeException(
                'Failed to send gRPC request: ' . ($client->errMsg ?: 'unknown error')
            );
        }

        $this->requestSent = true;
        $response = $this->callClient(static fn () => $client->recv($timeout));
        if ($response === false) {
            throw new RuntimeException(
                'Failed to receive gRPC response: ' . ($client->errMsg ?: 'timeout')
            );
        }

        $grpcStatus = $response->headers['grpc-status'] ?? '0';
        if ($grpcStatus !== '0') {
            $grpcMessage = $response->headers['grpc-message'] ?? 'Unknown error';

            return new ErrorFuture(new RuntimeException("gRPC error: {$grpcMessage}", (int) $grpcStatus));
        }

        return new CompletedFuture(null);
    }

    private function getClient(): Client
    {
        if ($this->client !== null && $this->client->connected) {
            return $this->client;
        }

        $this->resetClient();

        if ($this->clientFactory !== null) {
            $client = ($this->clientFactory)($this->host, $this->port, $this->ssl, $this->timeout);
            if (! $client instanceof Client) {
                throw new RuntimeException('gRPC client factory must return a Swoole HTTP/2 client');
            }

            $this->client = $client;

            return $this->client;
        }

        $this->client = new Client($this->host, $this->port, $this->ssl);
        $this->client->set([
            'timeout' => $this->timeout,
        ]);

        if (! $this->client->connect()) {
            $message = $this->client->errMsg;
            $this->client = null;

            throw new RuntimeException(
                "Failed to connect to {$this->host}:{$this->port}: " . $message
            );
        }

        return $this->client;
    }

    private function resetClient(): void
    {
        if ($this->client === null) {
            return;
        }

        $client = $this->client;
        $this->client = null;
        $client->close();
    }

    private function isRetryable(Throwable $error): bool
    {
        if ($this->requestSent) {
            return false;
        }

        $message = strtolower($error->getMessage());
        if (str_starts_with($message, 'failed to send grpc request:')) {
            return true;
        }

        foreach (['broken pipe', 'connection reset', 'connection closed', 'goaway'] as $fragment) {
            if (str_contains($message, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Swoole writes Client::$serverLastStreamId from recv() on PHP 8.2.
     * Swallow only that deprecation; every other diagnostic still propagates.
     */
    private function callClient(callable $operation): mixed
    {
        $previous = set_error_handler(static function (
            int $severity,
            string $message,
            string $file,
            int $line,
        ) use (&$previous): bool {
            if (
                $severity === E_DEPRECATED
                && str_contains($message, 'Swoole\Coroutine\Http2\Client::$serverLastStreamId')
            ) {
                return true;
            }

            if ($previous !== null) {
                return (bool) $previous($severity, $message, $file, $line);
            }

            return false;
        });

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
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
        return pack('CN', $compressed, strlen($data)) . $data;
    }
}
