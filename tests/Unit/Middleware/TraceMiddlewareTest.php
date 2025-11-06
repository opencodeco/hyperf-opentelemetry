<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\SemConv\Attributes\ClientAttributes;
use OpenTelemetry\SemConv\Attributes\HttpAttributes;
use OpenTelemetry\SemConv\Attributes\ServerAttributes;
use OpenTelemetry\SemConv\Attributes\UrlAttributes;
use OpenTelemetry\SemConv\Attributes\UserAgentAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\HttpIncubatingAttributes;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Middleware\TraceMiddleware;
use Hyperf\OpenTelemetry\Support\SpanScope;
use Hyperf\OpenTelemetry\Switcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * @internal
 */
class TraceMiddlewareTest extends TestCase
{
    private Switcher $switcher;

    private Instrumentation $instrumentation;

    private ConfigInterface $config;

    private ServerRequestInterface $request;

    private UriInterface $uri;

    private ResponseInterface $response;

    private RequestHandlerInterface $handler;

    private StreamInterface $responseBody;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(ConfigInterface::class);
        $this->switcher = $this->createMock(Switcher::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->uri = $this->createMock(UriInterface::class);
        $this->instrumentation = $this->createMock(Instrumentation::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->responseBody = $this->createMock(StreamInterface::class);

        $this->request->method('getUri')->willReturn($this->uri);
        $this->response->method('getBody')->willReturn($this->responseBody);

        $this->switcher->method('isTracingEnabled')->willReturn(true);

        $this->handler->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $this->config->method('get')
            ->willReturnMap([
                ['open-telemetry.instrumentation.features.client_request.options.ignore_paths', [], []],
                ['open-telemetry.instrumentation.features.client_request.options.headers.response', ['*'], ['*']],
                ['open-telemetry.traces.uri_mask', [], ['/P2P[0-9A-Za-z]+/' => '{identifier}']],
                ['open-telemetry.metrics.uri_mask', [], ['/P2P[0-9A-Za-z]+/' => '{identifier}']],
            ]);
    }

    public function testProcessWithIgnoredPath(): void
    {
        $this->uri->method('getPath')->willReturn('/health');

        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.instrumentation.features.client_request.options.ignore_paths', [])
            ->willReturn(['/^\/health$/']);

        $this->instrumentation->expects($this->never())->method('startSpan');

        $middleware = new TraceMiddleware(
            $config,
            $this->instrumentation,
            $this->switcher
        );

        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithTracingDisabled(): void
    {
        $this->uri->method('getPath')->willReturn('/test');

        $this->instrumentation->expects($this->never())->method('startSpan');

        $middleware = new TraceMiddleware(
            $this->config,
            $this->instrumentation,
            $this->createMock(Switcher::class)
        );

        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithSuccessfulRequest(): void
    {
        $this->configureRequestMock('GET', 'https://api.example.com:443/users/12?limit=10');
        $this->configureResponseMock(200);

        $spanScope = $this->createMock(SpanScope::class);

        $propagator = $this->createMock(TextMapPropagatorInterface::class);
        $propagator->expects($this->once())
            ->method('extract')
            ->with([
                'User-Agent' => ['TestAgent/1.0'],
                'x-forwarded-for' => ['192.168.1.100'],
                'remote-host' => [''],
                'x-real-ip' => [''],
            ])
            ->willReturn($context = $this->createMock(ContextInterface::class));

        $this->instrumentation->expects($this->once())->method('propagator')->willReturn($propagator);

        $this->instrumentation->expects($this->once())
            ->method('startSpan')
            ->with(
                '/users/{number}',
                SpanKind::KIND_SERVER,
                [
                    HttpAttributes::HTTP_REQUEST_METHOD => 'GET',
                    UrlAttributes::URL_FULL => 'https://api.example.com:443/users/12?limit=10',
                    UrlAttributes::URL_PATH => '/users/12',
                    UrlAttributes::URL_SCHEME => 'https',
                    UrlAttributes::URL_QUERY => 'limit=10',
                    ServerAttributes::SERVER_ADDRESS => 'api.example.com',
                    ServerAttributes::SERVER_PORT => 443,
                    UserAgentAttributes::USER_AGENT_ORIGINAL => 'TestAgent/1.0',
                    ClientAttributes::CLIENT_ADDRESS => '192.168.1.100',
                ],
                $this->isType('int'),
                $context
            )
            ->willReturn($spanScope);

        $spanScope->expects($this->once())
            ->method('setAttributes')
            ->with([
                HttpAttributes::HTTP_RESPONSE_STATUS_CODE => 200,
                HttpIncubatingAttributes::HTTP_RESPONSE_BODY_SIZE => '1024',
                'http.response.header.content-type' => 'application/json',
                'http.response.header.content-length' => '1024',
            ]);

        $spanScope->expects($this->once())->method('end');

        $middleware = new TraceMiddleware(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithUriMaskAndSuccessRequest(): void
    {
        $this->configureRequestMock('GET', 'https://api.example.com:443/users/P2P123?limit=10');
        $this->configureResponseMock(200);

        $spanScope = $this->createMock(SpanScope::class);

        $propagator = $this->createMock(TextMapPropagatorInterface::class);
        $propagator->expects($this->once())
            ->method('extract')
            ->with([
                'User-Agent' => ['TestAgent/1.0'],
                'x-forwarded-for' => ['192.168.1.100'],
                'remote-host' => [''],
                'x-real-ip' => [''],
            ])
            ->willReturn($context = $this->createMock(ContextInterface::class));

        $this->instrumentation->expects($this->once())->method('propagator')->willReturn($propagator);

        $this->instrumentation->expects($this->once())
            ->method('startSpan')
            ->with(
                '/users/{identifier}',
                SpanKind::KIND_SERVER,
                [
                    HttpAttributes::HTTP_REQUEST_METHOD => 'GET',
                    UrlAttributes::URL_FULL => 'https://api.example.com:443/users/P2P123?limit=10',
                    UrlAttributes::URL_PATH => '/users/P2P123',
                    UrlAttributes::URL_SCHEME => 'https',
                    UrlAttributes::URL_QUERY => 'limit=10',
                    ServerAttributes::SERVER_ADDRESS => 'api.example.com',
                    ServerAttributes::SERVER_PORT => 443,
                    UserAgentAttributes::USER_AGENT_ORIGINAL => 'TestAgent/1.0',
                    ClientAttributes::CLIENT_ADDRESS => '192.168.1.100',
                ],
                $this->isType('int'),
                $context
            )
            ->willReturn($spanScope);

        $spanScope->expects($this->once())
            ->method('setAttributes')
            ->with([
                HttpAttributes::HTTP_RESPONSE_STATUS_CODE => 200,
                HttpIncubatingAttributes::HTTP_RESPONSE_BODY_SIZE => '1024',
                'http.response.header.content-type' => 'application/json',
                'http.response.header.content-length' => '1024',
            ]);

        $spanScope->expects($this->once())->method('end');

        $middleware = new TraceMiddleware(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithException(): void
    {
        $this->configureRequestMock('POST', 'https://api.example.com:443/api/error');

        $exception = new RuntimeException('Test exception');

        $spanScope = $this->createMock(SpanScope::class);
        $this->instrumentation->expects($this->once())
            ->method('startSpan')
            ->willReturn($spanScope);

        $spanScope->expects($this->once())
            ->method('recordException')
            ->with($exception);

        $spanScope->expects($this->once())
            ->method('end');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willThrowException($exception);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $middleware = new TraceMiddleware(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $middleware->process($this->request, $handler);
    }

    private function configureRequestMock(string $method, string $url, array $headers = []): void
    {
        $parts = parse_url($url);

        $this->uri->method('getPath')->willReturn($parts['path'] ?? '/');
        $this->uri->method('getHost')->willReturn($parts['host']);
        $this->uri->method('getPort')->willReturn($parts['port'] ?? 80);
        $this->uri->method('getScheme')->willReturn($parts['scheme'] ?? 'http');
        $this->uri->method('getQuery')->willReturn($parts['query'] ?? '');
        $this->uri->method('__toString')->willReturn($url);

        $headers = array_merge([
            'User-Agent' => ['TestAgent/1.0'],
            'x-forwarded-for' => ['192.168.1.100'],
            'remote-host' => [''],
            'x-real-ip' => [''],
        ], $headers);

        $this->request->method('getMethod')->willReturn($method);
        $this->request->method('getHeaderLine')
            ->willReturnMap(array_map(function ($key, $value) {
                return [$key, is_array($value) ? current($value) : $value];
            }, array_keys($headers), array_values($headers)));

        $this->request->method('getHeaders')->willReturn($headers);

        $this->request->method('getServerParams')->willReturn([]);
    }

    private function configureResponseMock(int $statusCode, int $bodySize = 1024): void
    {
        $this->response->method('getStatusCode')->willReturn($statusCode);
        $this->responseBody->method('getSize')->willReturn($bodySize);
        $this->response->method('getHeaders')->willReturn([
            'Content-Type' => ['application/json'],
            'Content-Length' => [$bodySize],
        ]);
    }
}
