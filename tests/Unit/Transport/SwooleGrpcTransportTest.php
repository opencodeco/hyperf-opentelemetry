<?php

declare(strict_types=1);

namespace Tests\Unit\Transport;

use Closure;
use Hyperf\OpenTelemetry\Transport\SwooleGrpcTransport;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Swoole\Coroutine\Http2\Client;
use Swoole\Http2\Response;

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
        $stale = $this->mockClient();
        $stale->errMsg = 'broken pipe';
        $stale->method('send')->willReturn(false);
        $stale->method('close')->willReturn(true);

        // Second attempt also fails — deterministic, no real network call.
        $second = $this->mockClient();
        $second->errMsg = 'broken pipe';
        $second->method('send')->willReturn(false);
        $second->method('close')->willReturn(true);

        $calls = 0;
        $transport = $this->makeTransport(
            timeout: 0.05,
            clientFactory: function () use ($stale, $second, &$calls): Client {
                return $calls++ === 0 ? $stale : $second;
            }
        );

        $future = $transport->send('test payload');

        $reflection = new ReflectionClass($transport);
        $this->assertNull($reflection->getProperty('client')->getValue($transport));
        $this->expectException(RuntimeException::class);
        $future->await();
    }

    public function testSendRetriesAndSucceedsAfterInitialSendFailure(): void
    {
        $stale = $this->mockClient();
        $stale->errMsg = 'broken pipe';
        $stale->method('send')->willReturn(false);
        $stale->method('close')->willReturn(true);

        $response = new Response();
        $response->headers = ['grpc-status' => '0'];

        $fresh = $this->mockClient();
        $fresh->method('send')->willReturn(1);
        $fresh->method('recv')->willReturn($response);

        $calls = 0;
        $transport = $this->makeTransport(
            clientFactory: function () use ($stale, $fresh, &$calls): Client {
                return $calls++ === 0 ? $stale : $fresh;
            }
        );

        $future = $transport->send('test payload');
        $this->assertNull($future->await());
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

    private function makeTransport(float $timeout = 10.0, ?Closure $clientFactory = null): SwooleGrpcTransport
    {
        return new SwooleGrpcTransport(
            host: 'localhost',
            port: 4317,
            method: '/opentelemetry.proto.collector.trace.v1.TraceService/Export',
            timeout: $timeout,
            clientFactory: $clientFactory,
        );
    }

    private function injectClient(SwooleGrpcTransport $transport, Client $client): ReflectionClass
    {
        $reflection = new ReflectionClass($transport);
        $reflection->getProperty('client')->setValue($transport, $client);
        return $reflection;
    }

    private function mockClient(bool $connectSucceeds = true): Client
    {
        $mock = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock->connected = false;
        $mock->errMsg = '';
        $mock->method('set')->willReturn(true);
        $mock->method('connect')->willReturn($connectSucceeds);
        return $mock;
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
