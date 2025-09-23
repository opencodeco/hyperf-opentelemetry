<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Middleware;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\Attributes\ClientAttributes;
use OpenTelemetry\SemConv\Attributes\HttpAttributes;
use OpenTelemetry\SemConv\Attributes\ServerAttributes;
use OpenTelemetry\SemConv\Attributes\UrlAttributes;
use OpenTelemetry\SemConv\Attributes\UserAgentAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\HttpIncubatingAttributes;
use Hyperf\OpenTelemetry\Support\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class TraceMiddleware extends AbstractMiddleware
{
    /**
     * @throws Throwable
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if ($this->shouldIgnorePath($path) || ! $this->isTracingEnabled) {
            return $handler->handle($request);
        }

        $context = $this->instrumentation->propagator()->extract($request->getHeaders());
        $name = Uri::sanitize($path);

        $scope = $this->instrumentation->startSpan(
            name: $name,
            spanKind: SpanKind::KIND_SERVER,
            attributes: [
                HttpAttributes::HTTP_REQUEST_METHOD => $request->getMethod(),
                UrlAttributes::URL_FULL => (string) $request->getUri(),
                UrlAttributes::URL_PATH => $request->getUri()->getPath(),
                UrlAttributes::URL_SCHEME => $request->getUri()->getScheme(),
                UrlAttributes::URL_QUERY => $request->getUri()->getQuery(),
                ServerAttributes::SERVER_ADDRESS => $request->getUri()->getHost(),
                ServerAttributes::SERVER_PORT => $request->getUri()->getPort(),
                UserAgentAttributes::USER_AGENT_ORIGINAL => $request->getHeaderLine('User-Agent'),
                ClientAttributes::CLIENT_ADDRESS => $this->getRequestIP($request),
            ],
            explicitContext: $context
        );

        try {
            $response = $handler->handle($request);

            $scope->setAttributes([
                HttpAttributes::HTTP_RESPONSE_STATUS_CODE => $response->getStatusCode(),
                HttpIncubatingAttributes::HTTP_RESPONSE_BODY_SIZE => $response->getBody()->getSize(),
                ...$this->transformHeaders('response', $response->getHeaders()),
            ]);

            return $response;
        } catch (Throwable $exception) {
            $scope->recordException($exception);

            throw $exception;
        } finally {
            $scope->end();
        }
    }

    protected function featureName(): string
    {
        return 'client_request';
    }
}
