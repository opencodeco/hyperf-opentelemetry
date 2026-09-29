<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Support;

use Hyperf\HttpMessage\Exception\HttpException;
use ReflectionMethod;
use ReflectionProperty;
use Swoole\Http\Status;
use Throwable;

/**
 * Resolves an HTTP status from exceptions that do not share one type.
 *
 * Hyperf's validation failure is a RuntimeException with a public int $status,
 * while HttpException exposes getStatusCode(). Server spans treat only 5xx as errors.
 */
final class HttpStatusCode
{
    public static function fromException(Throwable $exception): int
    {
        return self::read($exception) ?? Status::INTERNAL_SERVER_ERROR;
    }

    public static function fromExceptionOrCode(Throwable $exception): int
    {
        return self::read($exception) ?? self::fromExceptionCode($exception) ?? Status::INTERNAL_SERVER_ERROR;
    }

    private static function read(Throwable $exception): ?int
    {
        if ($exception instanceof HttpException) {
            $status = self::normalize($exception->getStatusCode());
            if ($status !== null) {
                return $status;
            }
        }

        $status = self::fromGetStatusCode($exception);
        if ($status !== null) {
            return $status;
        }

        return self::fromStatusProperty($exception);
    }

    private static function fromGetStatusCode(Throwable $exception): ?int
    {
        if (! method_exists($exception, 'getStatusCode')) {
            return null;
        }

        $method = new ReflectionMethod($exception, 'getStatusCode');
        if (! $method->isPublic() || $method->getNumberOfRequiredParameters() > 0) {
            return null;
        }

        return self::normalize($exception->getStatusCode());
    }

    private static function fromStatusProperty(Throwable $exception): ?int
    {
        if (! property_exists($exception, 'status')) {
            return null;
        }

        $property = new ReflectionProperty($exception, 'status');
        if (! $property->isPublic() || ! $property->isInitialized($exception)) {
            return null;
        }

        return self::normalize($property->getValue($exception));
    }

    private static function fromExceptionCode(Throwable $exception): ?int
    {
        $code = $exception->getCode();
        if (! is_int($code) || Status::getReasonPhrase($code) === 'Unknown') {
            return null;
        }

        return $code;
    }

    private static function normalize(mixed $status): ?int
    {
        if (! is_int($status) || $status < 100 || $status > 599) {
            return null;
        }

        return $status;
    }
}
