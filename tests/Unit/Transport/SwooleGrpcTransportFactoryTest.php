<?php

declare(strict_types=1);

namespace Tests\Unit\Transport;

use Hyperf\OpenTelemetry\Transport\SwooleGrpcTransportFactory;
use InvalidArgumentException;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
class SwooleGrpcTransportFactoryTest extends TestCase
{
    public function testCreateWithValidEndpoint(): void
    {
        $factory = new SwooleGrpcTransportFactory();

        $transport = $factory->create(
            endpoint: 'http://localhost:4317/opentelemetry.proto.collector.trace.v1.TraceService/Export',
            contentType: ContentTypes::PROTOBUF,
        );

        $this->assertSame(ContentTypes::PROTOBUF, $transport->contentType());
    }

    public function testCreateWithHttpsEndpoint(): void
    {
        $factory = new SwooleGrpcTransportFactory();

        $transport = $factory->create(
            endpoint: 'https://collector.example.com:443/opentelemetry.proto.collector.trace.v1.TraceService/Export',
            contentType: ContentTypes::PROTOBUF,
        );

        $this->assertSame(ContentTypes::PROTOBUF, $transport->contentType());
    }

    public function testCreateThrowsExceptionForUnsupportedContentType(): void
    {
        $factory = new SwooleGrpcTransportFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported content type');

        $factory->create(
            endpoint: 'http://localhost:4317/opentelemetry.proto.collector.trace.v1.TraceService/Export',
            contentType: 'application/json',
        );
    }

    public function testCreateThrowsExceptionForInvalidEndpoint(): void
    {
        $factory = new SwooleGrpcTransportFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Endpoint must contain scheme, host, and path');

        $factory->create(
            endpoint: 'invalid-endpoint',
            contentType: ContentTypes::PROTOBUF,
        );
    }

    public function testCreateThrowsExceptionForInvalidGrpcMethod(): void
    {
        $factory = new SwooleGrpcTransportFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a valid gRPC method');

        $factory->create(
            endpoint: 'http://localhost:4317/invalid-path',
            contentType: ContentTypes::PROTOBUF,
        );
    }

    public function testCreateWithGrpcScheme(): void
    {
        $factory = new SwooleGrpcTransportFactory();

        $transport = $factory->create(
            endpoint: 'grpc://localhost:4317/opentelemetry.proto.collector.trace.v1.TraceService/Export',
            contentType: ContentTypes::PROTOBUF,
        );

        $this->assertSame(ContentTypes::PROTOBUF, $transport->contentType());
    }

    public function testCreateWithGrpcsScheme(): void
    {
        $factory = new SwooleGrpcTransportFactory();

        $transport = $factory->create(
            endpoint: 'grpcs://localhost:4317/opentelemetry.proto.collector.trace.v1.TraceService/Export',
            contentType: ContentTypes::PROTOBUF,
        );

        $this->assertSame(ContentTypes::PROTOBUF, $transport->contentType());
    }

    public function testCreateWithCompressionNoneTreatedAsNull(): void
    {
        $factory = new SwooleGrpcTransportFactory();

        $transport = $factory->create(
            endpoint: 'http://localhost:4317/opentelemetry.proto.collector.trace.v1.TraceService/Export',
            contentType: ContentTypes::PROTOBUF,
            compression: 'none',
        );

        $this->assertSame(ContentTypes::PROTOBUF, $transport->contentType());

        // Use reflection to verify compression was normalized to null
        $reflection = new ReflectionClass($transport);
        $compressionProp = $reflection->getProperty('compression');
        $this->assertNull($compressionProp->getValue($transport));
    }

    public function testCreateWithRetryParameters(): void
    {
        $factory = new SwooleGrpcTransportFactory();

        $transport = $factory->create(
            endpoint: 'http://localhost:4317/opentelemetry.proto.collector.trace.v1.TraceService/Export',
            contentType: ContentTypes::PROTOBUF,
            retryDelay: 200,
            maxRetries: 5,
        );

        $reflection = new ReflectionClass($transport);

        $this->assertSame(200, $reflection->getProperty('retryDelay')->getValue($transport));
        $this->assertSame(5, $reflection->getProperty('maxRetries')->getValue($transport));
    }

    public function testCreateThrowsExceptionForUnsupportedCompression(): void
    {
        $factory = new SwooleGrpcTransportFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported compression type "br"');

        $factory->create(
            endpoint: 'http://localhost:4317/opentelemetry.proto.collector.trace.v1.TraceService/Export',
            contentType: ContentTypes::PROTOBUF,
            compression: 'br',
        );
    }
}
