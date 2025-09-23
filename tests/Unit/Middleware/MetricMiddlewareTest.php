<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use OpenTelemetry\SemConv\Attributes\HttpAttributes;
use OpenTelemetry\SemConv\Metrics\HttpMetrics;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Middleware\MetricMiddleware;
use Hyperf\OpenTelemetry\Switcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * @internal
 */
class MetricMiddlewareTest extends TestCase
{
    private ConfigInterface $config;

    private Instrumentation $instrumentation;

    private Switcher $switcher;

    private MeterInterface $meter;

    private HistogramInterface $histogram;

    private ServerRequestInterface $request;

    private UriInterface $uri;

    private ResponseInterface $response;

    private RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(ConfigInterface::class);
        $this->instrumentation = $this->createMock(Instrumentation::class);
        $this->switcher = $this->createMock(Switcher::class);
        $this->meter = $this->createMock(MeterInterface::class);
        $this->histogram = $this->createMock(HistogramInterface::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->uri = $this->createMock(UriInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);

        $this->switcher->method('isMetricsEnabled')->willReturn(true);

        $this->config->method('get')
            ->with('open-telemetry.instrumentation.features.client_request.options.ignore_paths', [])
            ->willReturn([]);

        $this->request->method('getUri')->willReturn($this->uri);
        $this->instrumentation->method('meter')->willReturn($this->meter);

        $this->handler->method('handle')
            ->with($this->request)
            ->willReturn($this->response);
    }

    public function testProcessWithSuccessfulRequest(): void
    {
        $this->configureRequestMock('GET', '/users/12');
        $this->configureResponseMock(200);

        $this->meter->method('createHistogram')
            ->with(HttpMetrics::HTTP_SERVER_REQUEST_DURATION, 'ms')
            ->willReturn($this->histogram);

        $expectedAttributes = [
            HttpAttributes::HTTP_ROUTE => '/users/{number}',
            HttpAttributes::HTTP_REQUEST_METHOD => 'GET',
            HttpAttributes::HTTP_RESPONSE_STATUS_CODE => '200',
        ];

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                $this->greaterThan(0),
                $expectedAttributes
            );

        $middleware = new MetricMiddleware(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithIgnoredPath(): void
    {
        $this->configureRequestMock('GET', '/health');

        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.instrumentation.features.client_request.options.ignore_paths', [])
            ->willReturn(['/^\/health$/']);

        $this->histogram->expects($this->never())->method('record');

        $middleware = new MetricMiddleware(
            $config,
            $this->instrumentation,
            $this->switcher
        );

        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithMetricsDisabled(): void
    {
        $this->uri->method('getPath')->willReturn('/api/test');

        $this->histogram->expects($this->never())->method('record');

        $middleware = new MetricMiddleware(
            $this->config,
            $this->instrumentation,
            $this->createMock(Switcher::class)
        );

        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithException(): void
    {
        $path = '/api/error';
        $method = 'POST';
        $exception = new RuntimeException('Test exception');

        $this->uri->method('getPath')->willReturn($path);
        $this->request->method('getMethod')->willReturn($method);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $this->meter->method('createHistogram')
            ->with(HttpMetrics::HTTP_SERVER_REQUEST_DURATION, 'ms')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                $this->greaterThan(0),
                [
                    HttpAttributes::HTTP_ROUTE => $path,
                    HttpAttributes::HTTP_REQUEST_METHOD => $method,
                    ErrorAttributes::ERROR_TYPE => RuntimeException::class,
                    HttpAttributes::HTTP_RESPONSE_STATUS_CODE => '500',
                ]
            );

        $middleware = new MetricMiddleware(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $middleware->process($this->request, $handler);
    }

    private function configureRequestMock(string $method, string $path): void
    {
        $this->uri->method('getPath')->willReturn($path);
        $this->request->method('getMethod')->willReturn($method);
    }

    private function configureResponseMock(int $statusCode): void
    {
        $this->response->method('getStatusCode')->willReturn($statusCode);
    }
}
