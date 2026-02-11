<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Transport;

use InvalidArgumentException;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Common\Export\TransportInterface;

final class SwooleGrpcTransportFactory implements TransportFactoryInterface
{
    public function create(
        string $endpoint,
        string $contentType = ContentTypes::PROTOBUF,
        array $headers = [],
        $compression = null,
        float $timeout = 10.,
        int $retryDelay = 100,
        int $maxRetries = 3,
        ?string $cacert = null,
        ?string $cert = null,
        ?string $key = null,
    ): TransportInterface {
        if ($contentType !== ContentTypes::PROTOBUF) {
            throw new InvalidArgumentException(
                sprintf('Unsupported content type "%s", gRPC transport supports only %s', $contentType, ContentTypes::PROTOBUF)
            );
        }

        $parsed = $this->parseEndpoint($endpoint);

        $grpcHeaders = $this->buildHeaders($headers);

        $compressionType = null;
        if ($compression !== null) {
            $compressionType = is_array($compression) ? ($compression[0] ?? null) : $compression;
        }

        return new SwooleGrpcTransport(
            host: $parsed['host'],
            port: $parsed['port'],
            method: $parsed['method'],
            headers: $grpcHeaders,
            timeout: $timeout,
            ssl: $parsed['ssl'],
            compression: $compressionType,
        );
    }

    private function parseEndpoint(string $endpoint): array
    {
        $parts = parse_url($endpoint);

        if (! isset($parts['scheme'], $parts['host'], $parts['path'])) {
            throw new InvalidArgumentException('Endpoint must contain scheme, host, and path');
        }

        $scheme = $parts['scheme'];
        if (! in_array($scheme, ['http', 'https', 'grpc', 'grpcs'], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported scheme "%s"', $scheme));
        }

        $ssl = in_array($scheme, ['https', 'grpcs'], true);
        $port = $parts['port'] ?? ($ssl ? 443 : 4317);
        $method = $parts['path'];

        if (substr_count($method, '/') !== 2) {
            throw new InvalidArgumentException(
                sprintf('Endpoint path is not a valid gRPC method: "%s"', $method)
            );
        }

        return [
            'host' => $parts['host'],
            'port' => $port,
            'method' => $method,
            'ssl' => $ssl,
        ];
    }

    private function buildHeaders(array $headers): array
    {
        $grpcHeaders = [];

        foreach ($headers as $key => $value) {
            $grpcHeaders[strtolower($key)] = $value;
        }

        return $grpcHeaders;
    }
}
