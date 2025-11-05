<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Aspect;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\Exception;
use Hyperf\Stringable\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use OpenTelemetry\SemConv\Attributes\HttpAttributes;
use OpenTelemetry\SemConv\Attributes\ServerAttributes;
use OpenTelemetry\SemConv\Attributes\UrlAttributes;
use OpenTelemetry\SemConv\Attributes\UserAgentAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\HttpIncubatingAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\UrlIncubatingAttributes;
use OpenTelemetry\SemConv\Metrics\HttpMetrics;
use Hyperf\OpenTelemetry\Propagator\HeadersPropagator;
use Hyperf\OpenTelemetry\Support\SpanScope;
use Hyperf\OpenTelemetry\Support\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class GuzzleClientAspect extends AbstractAspect
{
    public array $classes = [
        Client::class . '::transfer',
    ];

    /**
     * @throws Throwable
     * @throws Exception
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint): mixed
    {
        if (! $this->isTelemetryEnabled()) {
            return $proceedingJoinPoint->process();
        }

        /** @var RequestInterface $request */
        $request = $proceedingJoinPoint->arguments['keys']['request'];
        $method = $request->getMethod();
        $start = microtime(true);
        $scope = null;

        $exporterHosts = [
            parse_url(
                $this->config->get('open-telemetry.traces.exporters.otlp_http.options.endpoint', 'localhost'),
                PHP_URL_HOST
            ),
            parse_url(
                $this->config->get('open-telemetry.metrics.exporters.otlp_http.options.endpoint', 'localhost'),
                PHP_URL_HOST
            ),
        ];

        if (in_array($request->getUri()->getHost(), $exporterHosts, true)) {
            return $proceedingJoinPoint->process();
        }

        $path = Uri::sanitize($request->getUri()->getPath(), $this->config->get('open-telemetry.traces.uri_mask', []));

        if ($this->isTracingEnabled) {
            $scope = $this->instrumentation->startSpan(
                name: $method . ' ' . $path,
                spanKind: SpanKind::KIND_CLIENT,
                attributes: [
                    HttpAttributes::HTTP_REQUEST_METHOD => $method,
                    UrlAttributes::URL_FULL => (string) $request->getUri(),
                    UrlAttributes::URL_PATH => $path,
                    UrlAttributes::URL_SCHEME => $request->getUri()->getScheme(),
                    UrlAttributes::URL_QUERY => $request->getUri()->getQuery(),
                    ServerAttributes::SERVER_ADDRESS => $request->getUri()->getHost(),
                    ServerAttributes::SERVER_PORT => $request->getUri()->getPort(),
                    UserAgentAttributes::USER_AGENT_ORIGINAL => $request->getHeaderLine('User-Agent'),
                    ...$this->transformHeaders('request', $request->getHeaders()),
                ]
            );

            $this->instrumentation->propagator()
                ->inject($request, HeadersPropagator::instance(), $scope->getContext());

            $proceedingJoinPoint->arguments['keys']['request'] = $request;
        }

        $promise = $proceedingJoinPoint->process();

        $scope?->detach();

        if ($promise instanceof PromiseInterface) {
            $promise->then(
                $this->onFullFilled($scope, $request, $start),
                $this->onRejected($scope, $request, $start)
            );
        }

        return $promise;
    }

    protected function featureName(): string
    {
        return 'guzzle';
    }

    private function onFullFilled(?SpanScope $scope, RequestInterface $request, float $start): callable
    {
        return function (ResponseInterface $response) use ($scope, $request, $start) {
            if ($scope) {
                $scope->setAttributes([
                    HttpAttributes::HTTP_RESPONSE_STATUS_CODE => $response->getStatusCode(),
                    HttpIncubatingAttributes::HTTP_RESPONSE_BODY_SIZE => $response->getHeaderLine('Content-Length'),
                    ...$this->transformHeaders('response', $response->getHeaders()),
                ]);

                if ($response->getStatusCode() >= 400) {
                    $scope->setStatus(StatusCode::STATUS_ERROR);
                }

                $scope->end();
            }

            if ($this->isMetricsEnabled) {
                $duration = (microtime(true) - $start) * 1000;
                $this->instrumentation->meter()
                    ->createHistogram('http.client.request.duration', 'ms')
                    ->record($duration, [
                        ServerAttributes::SERVER_ADDRESS => $request->getUri()->getHost(),
                        HttpAttributes::HTTP_REQUEST_METHOD => $request->getMethod(),
                        HttpAttributes::HTTP_RESPONSE_STATUS_CODE => $response->getStatusCode(),
                        UrlIncubatingAttributes::URL_TEMPLATE => Uri::sanitize(
                            $request->getUri()->getPath(),
                            $this->config->get('open-telemetry.metrics.uri_mask', []),
                        ),
                    ]);
            }

            return $response;
        };
    }

    private function onRejected(?SpanScope $scope, RequestInterface $request, float $start): callable
    {
        return function (Throwable $throwable) use ($scope, $request, $start): void {
            if ($scope) {
                $scope->recordException($throwable);
                $scope->end();
            }

            if ($this->isMetricsEnabled) {
                $duration = (microtime(true) - $start) * 1000;
                $this->instrumentation->meter()
                    ->createHistogram(HttpMetrics::HTTP_CLIENT_REQUEST_DURATION, 'ms')
                    ->record(
                        $duration,
                        [
                            ServerAttributes::SERVER_ADDRESS => $request->getUri()->getHost(),
                            HttpAttributes::HTTP_REQUEST_METHOD => $request->getMethod(),
                            HttpAttributes::HTTP_RESPONSE_STATUS_CODE => 0,
                            ErrorAttributes::ERROR_TYPE => get_class($throwable),
                            UrlIncubatingAttributes::URL_TEMPLATE => Uri::sanitize(
                                $request->getUri()->getPath(),
                                $this->config->get('open-telemetry.metrics.uri_mask', []),
                            ),
                        ]
                    );
            }

            throw $throwable;
        };
    }

    /**
     * Transform headers to OpenTelemetry attributes.
     *
     * @param array<array<string>> $headers
     * @return array<string, string>
     */
    private function transformHeaders(string $type, array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            $key = Str::lower($key);
            if ($this->canTransformHeaders($type, $key)) {
                $result["http.{$type}.header.{$key}"] = implode(', ', $value);
            }
        }

        return $result;
    }

    private function canTransformHeaders(string $type, string $key): bool
    {
        $headers = (array) $this->config->get(
            "open-telemetry.instrumentation.features.guzzle.options.headers.{$type}",
            ['*']
        );

        foreach ($headers as $header) {
            if (Str::is(Str::lower($header), $key)) {
                return true;
            }
        }

        return false;
    }
}
