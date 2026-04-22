<?php

declare(strict_types=1);

namespace Tests\Unit\Transport;

use Hyperf\OpenTelemetry\Transport\SwooleGrpcTransport;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Swoole\Coroutine\Http2\Client;

/**
 * @internal
 */
class SwooleGrpcTransportTest extends TestCase
{
    public function testContentTypeReturnsProtobuf(): void
    {
        $transport = $this->makeTransport();

        $this->assertSame(ContentTypes::PROTOBUF, $transport->contentType());
    }

    public function testShutdownReturnsTrueOnFirstCall(): void
    {
        $transport = $this->makeTransport();

        $this->assertTrue($transport->shutdown());
    }

    public function testShutdownReturnsFalseOnSecondCall(): void
    {
        $transport = $this->makeTransport();

        $transport->shutdown();
        $this->assertFalse($transport->shutdown());
    }

    public function testForceFlushReturnsTrueWhenNotClosed(): void
    {
        $transport = $this->makeTransport();

        $this->assertTrue($transport->forceFlush());
    }

    public function testForceFlushReturnsFalseWhenClosed(): void
    {
        $transport = $this->makeTransport();

        $transport->shutdown();
        $this->assertFalse($transport->forceFlush());
    }

    public function testSendReturnsErrorFutureWhenClosed(): void
    {
        $transport = $this->makeTransport();

        $transport->shutdown();

        $future = $transport->send('test payload');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transport is closed');
        $future->await();
    }

    public function testSendResetsClientWhenStreamIdIsFalse(): void
    {
        $transport = $this->makeTransport(timeout: 0.05);

        $mock = $this->mockConnectedClient();
        $mock->errMsg = 'broken pipe';
        $mock->method('send')->willReturn(false);
        $mock->method('close')->willReturn(true);

        $reflection = $this->injectClient($transport, $mock);

        $future = $transport->send('test payload');

        $this->assertNull($reflection->getProperty('client')->getValue($transport));
        $this->expectException(RuntimeException::class);
        $future->await();
    }

    public function testSendResetsClientWhenRecvReturnsFalse(): void
    {
        $transport = $this->makeTransport(timeout: 0.05);

        $mock = $this->mockConnectedClient();
        $mock->errMsg = 'timeout';
        $mock->method('send')->willReturn(1);
        $mock->method('recv')->willReturn(false);
        $mock->method('close')->willReturn(true);

        $reflection = $this->injectClient($transport, $mock);

        $future = $transport->send('test payload');

        $this->assertNull($reflection->getProperty('client')->getValue($transport));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to receive gRPC response');
        $future->await();
    }

    public function testSendDoesNotRetryAfterRecvFailure(): void
    {
        $transport = $this->makeTransport(timeout: 0.05);

        $mock = $this->mockConnectedClient();
        $mock->method('send')->willReturn(1);
        $mock->expects($this->once())->method('recv')->willReturn(false);
        $mock->method('close')->willReturn(true);

        $this->injectClient($transport, $mock);

        $transport->send('test payload');
    }

    private function makeTransport(float $timeout = 10.0): SwooleGrpcTransport
    {
        return new SwooleGrpcTransport(
            host: 'localhost',
            port: 4317,
            method: '/opentelemetry.proto.collector.trace.v1.TraceService/Export',
            timeout: $timeout,
        );
    }

    private function injectClient(SwooleGrpcTransport $transport, Client $client): ReflectionClass
    {
        $reflection = new ReflectionClass($transport);
        $reflection->getProperty('client')->setValue($transport, $client);
        return $reflection;
    }

    private function mockConnectedClient(): Client
    {
        $mock = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock->connected = true;
        $mock->errMsg = '';
        return $mock;
    }
}
