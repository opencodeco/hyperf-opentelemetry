<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Middleware;

use Hyperf\OpenTelemetry\Support\Uri;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use OpenTelemetry\SemConv\Attributes\HttpAttributes;
use OpenTelemetry\SemConv\Metrics\HttpMetrics;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Swoole\Http\Status;
use Throwable;

class MetricMiddleware extends AbstractMiddleware
{
    /**
     * @throws Throwable
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if ($this->shouldIgnorePath($path) || ! $this->isMetricsEnabled) {
            return $handler->handle($request);
        }

        $startTime = microtime(true);

        $attributes = [
            HttpAttributes::HTTP_REQUEST_METHOD => $request->getMethod(),
            HttpAttributes::HTTP_ROUTE => Uri::sanitize(
                $request->getUri()->getPath(),
                $this->config->get('open-telemetry.metrics.uri_mask', [])
            ),
        ];

        try {
            $response = $handler->handle($request);

            $attributes[HttpAttributes::HTTP_RESPONSE_STATUS_CODE] = $response->getStatusCode();

            return $response;
        } catch (Throwable $exception) {
            $attributes[ErrorAttributes::ERROR_TYPE] = get_class($exception);
            $attributes[HttpAttributes::HTTP_RESPONSE_STATUS_CODE] = $this->getHttpStatusCodeForException($exception);

            throw $exception;
        } finally {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->instrumentation->meter()
                ->createHistogram(HttpMetrics::HTTP_SERVER_REQUEST_DURATION, 'ms')
                ->record($duration, $attributes);
        }
    }

    protected function featureName(): string
    {
        return 'client_request';
    }

    protected function getHttpStatusCodeForException(Throwable $exception): int
    {
        $exceptionCode = $exception->getCode();
        $isHttpExceptionCode = is_int($exceptionCode) && Status::getReasonPhrase($exceptionCode) !== 'Unknown';

        return $isHttpExceptionCode ? $exceptionCode : Status::INTERNAL_SERVER_ERROR;
    }
}
