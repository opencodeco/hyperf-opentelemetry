<?php

declare(strict_types=1);

namespace Tests\Unit\Transport;

use Closure;
use Hyperf\OpenTelemetry\Transport\SwooleGrpcTransport;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Swoole\Coroutine\Http2\Client;
use Swoole\Http2\Request;
use Swoole\Http2\Response;
use Throwable;

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

    public function testSendReconnectsWhenConnectedClientHitsBrokenPipe(): void
    {
        $first = new FakeHttp2Client(streamId: false, error: 'Broken pipe');
        $second = new FakeHttp2Client(streamId: 1);
        $created = 0;
        $transport = $this->transport(function () use (&$created, $first, $second): Client {
            return $created++ === 0 ? $first : $second;
        });

        $this->assertNull($transport->send('payload')->await());
        $this->assertSame(2, $created);
        $this->assertTrue($first->wasClosed);
        $this->assertFalse($second->wasClosed);
        $this->assertSame($second, $this->storedClient($transport));
    }

    public function testSendReturnsErrorAndClearsClientOnNonRetryableFailure(): void
    {
        $client = new FakeHttp2Client(throwable: new RuntimeException('Invalid HTTP/2 frame'));
        $created = 0;
        $transport = $this->transport(function () use (&$created, $client): Client {
            ++$created;

            return $client;
        });

        try {
            $transport->send('payload')->await();
            $this->fail('Expected a non-retryable send failure');
        } catch (RuntimeException $e) {
            $this->assertSame('Invalid HTTP/2 frame', $e->getMessage());
        }

        $this->assertSame(1, $created);
        $this->assertTrue($client->wasClosed);
        $this->assertNull($this->storedClient($transport));
    }

    public function testSendRetriesOnlyOnceWhenTheRebuiltClientAlsoFails(): void
    {
        $first = new FakeHttp2Client(streamId: false, error: 'Connection reset by peer');
        $second = new FakeHttp2Client(streamId: false, error: 'GOAWAY');
        $created = 0;
        $transport = $this->transport(function () use (&$created, $first, $second): Client {
            return $created++ === 0 ? $first : $second;
        });

        try {
            $transport->send('payload')->await();
            $this->fail('Expected the second connection failure to surface');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('GOAWAY', $e->getMessage());
        }

        $this->assertSame(2, $created);
        $this->assertTrue($first->wasClosed);
        $this->assertTrue($second->wasClosed);
        $this->assertNull($this->storedClient($transport));
    }

    public function testSendDoesNotRetryAfterTheRequestWasWritten(): void
    {
        $client = new FakeHttp2Client(streamId: 1, error: 'Broken pipe', response: false);
        $created = 0;
        $transport = $this->transport(function () use (&$created, $client): Client {
            ++$created;

            return $client;
        });

        try {
            $transport->send('payload')->await();
            $this->fail('Expected the receive failure to surface');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Broken pipe', $e->getMessage());
        }

        $this->assertSame(1, $created);
        $this->assertTrue($client->wasClosed);
        $this->assertNull($this->storedClient($transport));
    }

    public function testSendSuppressesOnlyServerLastStreamIdDeprecation(): void
    {
        $seen = [];
        $transport = $this->transport(static fn (): Client => new DeprecatingHttp2Client());
        set_error_handler(static function (int $severity, string $message) use (&$seen): bool {
            $seen[] = [$severity, $message];

            return true;
        });

        try {
            $this->assertNull($transport->send('payload')->await());
        } finally {
            restore_error_handler();
        }

        $this->assertSame([
            [E_USER_WARNING, 'unrelated warning'],
        ], $seen);
    }

    private function transport(?Closure $clientFactory = null): SwooleGrpcTransport
    {
        return new SwooleGrpcTransport(
            host: '127.0.0.1',
            port: 4317,
            method: '/opentelemetry.proto.collector.trace.v1.TraceService/Export',
            clientFactory: $clientFactory,
        );
    }

    private function storedClient(SwooleGrpcTransport $transport): ?Client
    {
        $property = new ReflectionProperty(SwooleGrpcTransport::class, 'client');

        return $property->getValue($transport);
    }
}

class FakeHttp2Client extends Client
{
    public bool $wasClosed = false;

    public function __construct(
        private readonly false | int $streamId = 1,
        private readonly ?string $error = null,
        private readonly ?Throwable $throwable = null,
        private readonly false | Response | null $response = null,
    ) {
        parent::__construct('127.0.0.1', 9, false);
        $this->connected = true;
        if ($error !== null) {
            $this->errMsg = $error;
        }
    }

    public function send(Request $request): false | int
    {
        if ($this->throwable !== null) {
            throw $this->throwable;
        }

        return $this->streamId;
    }

    public function recv(float $timeout = 0): false | Response
    {
        if ($this->response === false) {
            $this->errMsg = $this->error ?? 'timeout';

            return false;
        }

        if ($this->response instanceof Response) {
            return $this->response;
        }

        $response = new Response();
        $response->headers = ['grpc-status' => '0'];

        return $response;
    }

    public function close(): bool
    {
        $this->wasClosed = true;
        $this->connected = false;

        return parent::close();
    }
}

class DeprecatingHttp2Client extends Client
{
    public function __construct()
    {
        parent::__construct('127.0.0.1', 9, false);
        $this->connected = true;
    }

    public function send(Request $request): false | int
    {
        $handler = set_error_handler(static fn (): bool => false);
        restore_error_handler();
        if (is_callable($handler)) {
            $handler(
                E_DEPRECATED,
                'Creation of dynamic property Swoole\Coroutine\Http2\Client::$serverLastStreamId is deprecated',
                __FILE__,
                __LINE__,
            );
            $handler(E_USER_WARNING, 'unrelated warning', __FILE__, __LINE__);
        }

        return 1;
    }

    public function recv(float $timeout = 0): false | Response
    {
        $response = new Response();
        $response->headers = ['grpc-status' => '0'];

        return $response;
    }
}
