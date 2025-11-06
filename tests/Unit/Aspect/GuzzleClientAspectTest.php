<?php

declare(strict_types=1);

namespace Tests\Unit\Aspect;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\PromiseInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use OpenTelemetry\SemConv\Attributes\HttpAttributes;
use OpenTelemetry\SemConv\Attributes\ServerAttributes;
use OpenTelemetry\SemConv\Attributes\UrlAttributes;
use OpenTelemetry\SemConv\Attributes\UserAgentAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\HttpIncubatingAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\UrlIncubatingAttributes;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Aspect\GuzzleClientAspect;
use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Support\SpanScope;
use Hyperf\OpenTelemetry\Switcher;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * @internal
 */
class GuzzleClientAspectTest extends TestCase
{
    private ConfigInterface $config;

    private Instrumentation $instrumentation;

    private Switcher $switcher;

    private ProceedingJoinPoint $proceedingJoinPoint;

    private SpanScope $spanScope;

    private RequestInterface $request;

    private ResponseInterface $response;

    private UriInterface $uri;

    private TextMapPropagatorInterface $propagator;

    private PromiseInterface $promise;

    private MeterInterface $meter;

    private HistogramInterface $histogram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(ConfigInterface::class);
        $this->instrumentation = $this->createMock(Instrumentation::class);
        $this->switcher = $this->createMock(Switcher::class);
        $this->proceedingJoinPoint = $this->createMock(ProceedingJoinPoint::class);
        $this->spanScope = $this->createMock(SpanScope::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->uri = $this->createMock(UriInterface::class);
        $this->propagator = $this->createMock(TextMapPropagatorInterface::class);
        $this->promise = $this->createMock(PromiseInterface::class);
        $this->meter = $this->createMock(MeterInterface::class);
        $this->histogram = $this->createMock(HistogramInterface::class);

        $this->request->method('getUri')->willReturn($this->uri);

        $this->switcher->method('isTracingEnabled')->willReturn(true);
        $this->switcher->method('isMetricsEnabled')->willReturn(true);

        $this->config->method('get')
            ->willReturnMap([
                ['open-telemetry.traces.exporters.otlp_http.options.endpoint', 'localhost', 'http://collector:4318'],
                ['open-telemetry.metrics.exporters.otlp_http.options.endpoint', 'localhost', 'http://collector:4318'],
                ['open-telemetry.instrumentation.features.guzzle.options.headers.request', ['*'], ['*']],
                ['open-telemetry.instrumentation.features.guzzle.options.headers.response', ['*'], ['*']],
                ['open-telemetry.traces.uri_mask', [], ['/P2P[0-9A-Za-z]+/' => '{identifier}']],
                ['open-telemetry.metrics.uri_mask', [], ['/P2P[0-9A-Za-z]+/' => '{identifier}']],
            ]);

        $this->proceedingJoinPoint->arguments = ['keys' => ['request' => $this->request]];

        $this->instrumentation->method('propagator')->willReturn($this->propagator);
        $this->instrumentation->method('meter')->willReturn($this->meter);

        $this->proceedingJoinPoint->method('process')->willReturn($this->promise);
    }

    public function testProcessWhenTelemetryIsDisabled(): void
    {
        $aspect = new GuzzleClientAspect(
            $this->config,
            $this->instrumentation,
            $this->createMock(Switcher::class)
        );

        $this->instrumentation->expects($this->never())->method('startSpan');
        $this->meter->expects($this->never())->method('createHistogram');

        $this->assertEquals($this->promise, $aspect->process($this->proceedingJoinPoint));
    }

    public function testProcessSkipsExporterHosts(): void
    {
        $this->uri->method('getHost')->willReturn('collector');

        $aspect = new GuzzleClientAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $this->instrumentation->expects($this->never())->method('startSpan');
        $this->meter->expects($this->never())->method('createHistogram');

        $this->assertEquals($this->promise, $aspect->process($this->proceedingJoinPoint));
    }

    public function testProcessWithSuccessRequest(): void
    {
        $this->configureRequestMock(
            'GET',
            'https://api.example.com/v1/users/12/transactions?page=1',
            ['User-Agent' => ['TestAgent/1.0']]
        );

        $this->configureResponseMock();

        $aspect = new GuzzleClientAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $this->promise->expects($this->once())
            ->method('then')
            ->willReturnCallback(function ($fullFilled, $rejected) {
                $this->assertIsCallable($fullFilled);
                $this->assertIsCallable($rejected);

                $fullFilled($this->response);

                return $this->promise;
            });

        // Span
        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with(
                $this->equalTo('GET /v1/users/{number}/transactions'),
                $this->equalTo(SpanKind::KIND_CLIENT),
                [
                    HttpAttributes::HTTP_REQUEST_METHOD => 'GET',
                    UrlAttributes::URL_FULL => 'https://api.example.com/v1/users/12/transactions?page=1',
                    UrlAttributes::URL_PATH => '/v1/users/{number}/transactions',
                    UrlAttributes::URL_SCHEME => 'https',
                    UrlAttributes::URL_QUERY => 'page=1',
                    ServerAttributes::SERVER_ADDRESS => 'api.example.com',
                    ServerAttributes::SERVER_PORT => 443,
                    UserAgentAttributes::USER_AGENT_ORIGINAL => 'TestAgent/1.0',
                    'http.request.header.user-agent' => 'TestAgent/1.0',
                ]
            )
            ->willReturn($this->spanScope);

        $this->propagator->expects($this->once())
            ->method('inject')
            ->with($this->request, $this->anything(), $this->spanScope->getContext());

        $this->spanScope->expects($this->once())->method('detach');

        $this->spanScope->expects($this->never())->method('setStatus');

        $this->spanScope->expects($this->once())
            ->method('setAttributes')
            ->with([
                HttpAttributes::HTTP_RESPONSE_STATUS_CODE => 200,
                HttpIncubatingAttributes::HTTP_RESPONSE_BODY_SIZE => '1024',
                'http.response.header.content-type' => 'application/json',
                'http.response.header.content-length' => '1024',
            ]);

        $this->spanScope->expects($this->once())->method('end');

        // Metric
        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->with('http.client.request.duration', 'ms')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                $this->isType('float'),
                [
                    ServerAttributes::SERVER_ADDRESS => 'api.example.com',
                    UrlIncubatingAttributes::URL_TEMPLATE => '/v1/users/{number}/transactions',
                    HttpAttributes::HTTP_REQUEST_METHOD => 'GET',
                    HttpAttributes::HTTP_RESPONSE_STATUS_CODE => 200,
                ]
            );

        $result = $aspect->process($this->proceedingJoinPoint);

        $this->assertEquals($this->promise, $result);
    }
    public function testProcessWithUriMaskAndSuccessRequest(): void
    {
        $this->configureRequestMock(
            'GET',
            'https://api.example.com/v1/users/P2P123/transactions?page=1',
            ['User-Agent' => ['TestAgent/1.0']]
        );

        $this->configureResponseMock();

        $aspect = new GuzzleClientAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $this->promise->expects($this->once())
            ->method('then')
            ->willReturnCallback(function ($fullFilled, $rejected) {
                $this->assertIsCallable($fullFilled);
                $this->assertIsCallable($rejected);

                $fullFilled($this->response);

                return $this->promise;
            });

        // Span
        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with(
                $this->equalTo('GET /v1/users/{identifier}/transactions'),
                $this->equalTo(SpanKind::KIND_CLIENT),
                [
                    HttpAttributes::HTTP_REQUEST_METHOD => 'GET',
                    UrlAttributes::URL_FULL => 'https://api.example.com/v1/users/P2P123/transactions?page=1',
                    UrlAttributes::URL_PATH => '/v1/users/{identifier}/transactions',
                    UrlAttributes::URL_SCHEME => 'https',
                    UrlAttributes::URL_QUERY => 'page=1',
                    ServerAttributes::SERVER_ADDRESS => 'api.example.com',
                    ServerAttributes::SERVER_PORT => 443,
                    UserAgentAttributes::USER_AGENT_ORIGINAL => 'TestAgent/1.0',
                    'http.request.header.user-agent' => 'TestAgent/1.0',
                ]
            )
            ->willReturn($this->spanScope);

        $this->propagator->expects($this->once())
            ->method('inject')
            ->with($this->request, $this->anything(), $this->spanScope->getContext());

        $this->spanScope->expects($this->once())->method('detach');

        $this->spanScope->expects($this->never())->method('setStatus');

        $this->spanScope->expects($this->once())
            ->method('setAttributes')
            ->with([
                HttpAttributes::HTTP_RESPONSE_STATUS_CODE => 200,
                HttpIncubatingAttributes::HTTP_RESPONSE_BODY_SIZE => '1024',
                'http.response.header.content-type' => 'application/json',
                'http.response.header.content-length' => '1024',
            ]);

        $this->spanScope->expects($this->once())->method('end');

        // Metric
        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->with('http.client.request.duration', 'ms')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                $this->isType('float'),
                [
                    ServerAttributes::SERVER_ADDRESS => 'api.example.com',
                    UrlIncubatingAttributes::URL_TEMPLATE => '/v1/users/{identifier}/transactions',
                    HttpAttributes::HTTP_REQUEST_METHOD => 'GET',
                    HttpAttributes::HTTP_RESPONSE_STATUS_CODE => 200,
                ]
            );

        $result = $aspect->process($this->proceedingJoinPoint);

        $this->assertEquals($this->promise, $result);
    }

    public function testProcessWithBadRequest(): void
    {
        $this->configureRequestMock(
            'POST',
            'https://api.example.com/v1/users',
            [
                'User-Agent' => ['TestAgent/2.0'],
                'Content-Type' => ['application/json'],
            ]
        );

        $this->configureResponseMock(400);

        $aspect = new GuzzleClientAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $this->promise->expects($this->once())
            ->method('then')
            ->willReturnCallback(function ($fullFilled, $rejected) {
                $this->assertIsCallable($fullFilled);
                $this->assertIsCallable($rejected);

                $fullFilled($this->response);

                return $this->promise;
            });

        // Span
        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with(
                $this->equalTo('POST /v1/users'),
                $this->equalTo(SpanKind::KIND_CLIENT),
                [
                    HttpAttributes::HTTP_REQUEST_METHOD => 'POST',
                    UrlAttributes::URL_FULL => 'https://api.example.com/v1/users',
                    UrlAttributes::URL_PATH => '/v1/users',
                    UrlAttributes::URL_SCHEME => 'https',
                    UrlAttributes::URL_QUERY => '',
                    ServerAttributes::SERVER_ADDRESS => 'api.example.com',
                    ServerAttributes::SERVER_PORT => 443,
                    UserAgentAttributes::USER_AGENT_ORIGINAL => 'TestAgent/2.0',
                    'http.request.header.user-agent' => 'TestAgent/2.0',
                    'http.request.header.content-type' => 'application/json',
                ]
            )
            ->willReturn($this->spanScope);

        $this->spanScope->expects($this->once())
            ->method('setStatus')
            ->with(StatusCode::STATUS_ERROR);

        $this->spanScope->expects($this->once())
            ->method('setAttributes')
            ->with([
                HttpAttributes::HTTP_RESPONSE_STATUS_CODE => 400,
                HttpIncubatingAttributes::HTTP_RESPONSE_BODY_SIZE => '1024',
                'http.response.header.content-type' => 'application/json',
                'http.response.header.content-length' => '1024',
            ]);

        $this->spanScope->expects($this->once())->method('end');

        // Metric
        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->with('http.client.request.duration', 'ms')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                $this->isType('float'),
                [
                    ServerAttributes::SERVER_ADDRESS => 'api.example.com',
                    UrlIncubatingAttributes::URL_TEMPLATE => '/v1/users',
                    HttpAttributes::HTTP_REQUEST_METHOD => 'POST',
                    HttpAttributes::HTTP_RESPONSE_STATUS_CODE => 400,
                ]
            );

        $result = $aspect->process($this->proceedingJoinPoint);

        $this->assertEquals($this->promise, $result);
    }

    public function testProcessWithRejectedRequest(): void
    {
        $this->configureRequestMock(
            'POST',
            'https://api.example.com/v1/users',
            [
                'User-Agent' => ['TestAgent/2.0'],
                'Content-Type' => ['application/json'],
            ]
        );
        $this->configureResponseMock(400);

        $aspect = new GuzzleClientAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $exception = $this->createMock(ConnectException::class);

        $this->promise->expects($this->once())
            ->method('then')
            ->willReturnCallback(function ($fullFilled, $rejected) use ($exception) {
                $this->assertIsCallable($fullFilled);
                $this->assertIsCallable($rejected);

                $rejected($exception);

                return $this->promise;
            });

        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with(
                $this->equalTo('POST /v1/users'),
                $this->equalTo(SpanKind::KIND_CLIENT),
                [
                    HttpAttributes::HTTP_REQUEST_METHOD => 'POST',
                    UrlAttributes::URL_FULL => 'https://api.example.com/v1/users',
                    UrlAttributes::URL_PATH => '/v1/users',
                    UrlAttributes::URL_SCHEME => 'https',
                    UrlAttributes::URL_QUERY => '',
                    ServerAttributes::SERVER_ADDRESS => 'api.example.com',
                    ServerAttributes::SERVER_PORT => 443,
                    UserAgentAttributes::USER_AGENT_ORIGINAL => 'TestAgent/2.0',
                    'http.request.header.user-agent' => 'TestAgent/2.0',
                    'http.request.header.content-type' => 'application/json',
                ]
            )
            ->willReturn($this->spanScope);

        $this->spanScope->expects($this->never())->method('setAttributes');

        $this->spanScope->expects($this->once())->method('recordException')->with($exception);

        $this->spanScope->expects($this->once())->method('end');

        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->with('http.client.request.duration', 'ms')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                $this->isType('float'),
                [
                    ServerAttributes::SERVER_ADDRESS => 'api.example.com',
                    UrlIncubatingAttributes::URL_TEMPLATE => '/v1/users',
                    HttpAttributes::HTTP_REQUEST_METHOD => 'POST',
                    HttpAttributes::HTTP_RESPONSE_STATUS_CODE => 0,
                    ErrorAttributes::ERROR_TYPE => $exception::class,
                ]
            );

        $this->expectExceptionObject($exception);

        $result = $aspect->process($this->proceedingJoinPoint);

        $this->assertEquals($this->promise, $result);
    }

    private function configureRequestMock(string $method, string $url, array $headers = []): void
    {
        $parts = parse_url($url);

        $this->uri->method('getPath')->willReturn($parts['path'] ?? '/');
        $this->uri->method('getHost')->willReturn($parts['host']);
        $this->uri->method('getPort')->willReturn($parts['port'] ?? 443);
        $this->uri->method('getScheme')->willReturn($parts['scheme'] ?? 'http');
        $this->uri->method('getQuery')->willReturn($parts['query'] ?? '');
        $this->uri->method('__toString')->willReturn($url);

        $this->request->method('getMethod')->willReturn($method);
        $this->request->method('getHeaderLine')
            ->willReturnMap(array_map(function ($key, $value) {
                return [$key, current($value)];
            }, array_keys($headers), array_values($headers)));

        $this->request->method('getHeaders')->willReturn($headers);
    }

    private function configureResponseMock(int $statusCode = 200): void
    {
        $this->response->method('getStatusCode')->willReturn($statusCode);
        $this->response->method('getHeaders')->willReturn([
            'Content-Type' => ['application/json'],
            'Content-Length' => ['1024'],
        ]);
        $this->response->method('getHeaderLine')->with('Content-Length')->willReturn('1024');
    }
}
