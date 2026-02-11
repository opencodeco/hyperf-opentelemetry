<?php

declare(strict_types=1);

namespace Tests\Unit\Transport;

use Hyperf\OpenTelemetry\Transport\SwooleGrpcTransport;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
class SwooleGrpcTransportTest extends TestCase
{
    public function testContentTypeReturnsProtobuf(): void
    {
        $transport = new SwooleGrpcTransport(
            host: 'localhost',
            port: 4317,
            method: '/opentelemetry.proto.collector.trace.v1.TraceService/Export',
        );

        $this->assertSame(ContentTypes::PROTOBUF, $transport->contentType());
    }

    public function testShutdownReturnsTrueOnFirstCall(): void
    {
        $transport = new SwooleGrpcTransport(
            host: 'localhost',
            port: 4317,
            method: '/opentelemetry.proto.collector.trace.v1.TraceService/Export',
        );

        $this->assertTrue($transport->shutdown());
    }

    public function testShutdownReturnsFalseOnSecondCall(): void
    {
        $transport = new SwooleGrpcTransport(
            host: 'localhost',
            port: 4317,
            method: '/opentelemetry.proto.collector.trace.v1.TraceService/Export',
        );

        $transport->shutdown();
        $this->assertFalse($transport->shutdown());
    }

    public function testForceFlushReturnsTrueWhenNotClosed(): void
    {
        $transport = new SwooleGrpcTransport(
            host: 'localhost',
            port: 4317,
            method: '/opentelemetry.proto.collector.trace.v1.TraceService/Export',
        );

        $this->assertTrue($transport->forceFlush());
    }

    public function testForceFlushReturnsFalseWhenClosed(): void
    {
        $transport = new SwooleGrpcTransport(
            host: 'localhost',
            port: 4317,
            method: '/opentelemetry.proto.collector.trace.v1.TraceService/Export',
        );

        $transport->shutdown();
        $this->assertFalse($transport->forceFlush());
    }

    public function testSendReturnsErrorFutureWhenClosed(): void
    {
        $transport = new SwooleGrpcTransport(
            host: 'localhost',
            port: 4317,
            method: '/opentelemetry.proto.collector.trace.v1.TraceService/Export',
        );

        $transport->shutdown();

        $future = $transport->send('test payload');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transport is closed');
        $future->await();
    }
}
