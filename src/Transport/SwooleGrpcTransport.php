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
use Swoole\Coroutine\Http2\Client;
use Swoole\Http2\Request;
use Throwable;

final class SwooleGrpcTransport implements TransportInterface
{
    private bool $closed = false;

    private ?Client $client = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $method,
        private readonly array $headers = [],
        private readonly float $timeout = 10.0,
        private readonly bool $ssl = false,
        private readonly ?string $compression = null,
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

        try {
            $client = $this->getClient();

            $data = $this->compress($payload);

            $request = new Request();
            $request->method = 'POST';
            $request->path = $this->method;
            $request->headers = $this->buildHeaders();
            $request->data = $this->packMessage($data);

            $streamId = $client->send($request);

            if ($streamId === false || $streamId <= 0) {
                return new ErrorFuture(new RuntimeException(
                    'Failed to send gRPC request: ' . ($client->errMsg ?: 'unknown error')
                ));
            }

            $response = $client->recv($this->timeout);

            if ($response === false) {
                return new ErrorFuture(new RuntimeException(
                    'Failed to receive gRPC response: ' . ($client->errMsg ?: 'timeout')
                ));
            }

            $grpcStatus = $response->headers['grpc-status'] ?? '0';
            if ($grpcStatus !== '0') {
                $grpcMessage = $response->headers['grpc-message'] ?? 'Unknown error';
                return new ErrorFuture(new RuntimeException("gRPC error: {$grpcMessage}", (int) $grpcStatus));
            }

            return new CompletedFuture(null);
        } catch (Throwable $e) {
            return new ErrorFuture($e);
        }
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

    private function getClient(): Client
    {
        if ($this->client === null || ! $this->client->connected) {
            $this->client = new Client($this->host, $this->port, $this->ssl);
            $this->client->set([
                'timeout' => $this->timeout,
            ]);

            if (! $this->client->connect()) {
                throw new RuntimeException(
                    "Failed to connect to {$this->host}:{$this->port}: " . $this->client->errMsg
                );
            }
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
        return pack('CN', $compressed, strlen($data)) . $data;
    }
}
