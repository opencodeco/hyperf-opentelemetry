<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Middleware;

use Hyperf\Stringable\Str;
use Hyperf\OpenTelemetry\Support\AbstractInstrumenter;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

abstract class AbstractMiddleware extends AbstractInstrumenter implements MiddlewareInterface
{
    protected function shouldIgnorePath(string $path): bool
    {
        $paths = $this->config->get(
            'open-telemetry.instrumentation.features.client_request.options.ignore_paths',
            []
        );

        if (empty($paths) || ! is_array($paths)) {
            return false;
        }

        foreach ($paths as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }
            $matched = preg_match($pattern, $path);
            if ($matched === 1) {
                return true;
            }
        }

        return false;
    }

    protected function transformHeaders(string $type, array $headers): array
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

    protected function canTransformHeaders(string $type, string $key): bool
    {
        $headers = (array) $this->config->get(
            "open-telemetry.instrumentation.features.client_request.options.headers.{$type}",
            ['*']
        );

        foreach ($headers as $header) {
            if (Str::is(Str::lower($header), $key)) {
                return true;
            }
        }

        return false;
    }

    protected function getRequestIP(ServerRequestInterface $request): string
    {
        $ips = $request->getHeaderLine('x-forwarded-for')
            ?: $request->getHeaderLine('remote-host')
                ?: $request->getHeaderLine('x-real-ip')
                    ?: $request->getServerParams()['remote_addr'] ?? '';

        return explode(',', $ips)[0] ?? '';
    }
}
