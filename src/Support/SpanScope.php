<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Support;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SemConv\Attributes\CodeAttributes;
use OpenTelemetry\SemConv\Attributes\ExceptionAttributes;
use Throwable;

class SpanScope
{
    public function __construct(
        private readonly SpanInterface $span,
        private readonly ScopeInterface $scope,
        private readonly ContextInterface $context
    ) {
    }

    public function recordException(?Throwable $throwable = null): void
    {
        if ($throwable === null) {
            return;
        }

        $trace = $throwable->getTrace()[0] ?? [];

        $this->span->setAttributes([
            ExceptionAttributes::EXCEPTION_TYPE => get_class($throwable),
            ExceptionAttributes::EXCEPTION_MESSAGE => $throwable->getMessage(),
            ExceptionAttributes::EXCEPTION_STACKTRACE => $throwable->getTraceAsString(),
            CodeAttributes::CODE_LINE_NUMBER => $throwable->getLine(),
            CodeAttributes::CODE_FUNCTION_NAME => isset($trace['class'], $trace['function'])
                ? "{$trace['class']}::{$trace['function']}"
                : ($trace['function'] ?? 'unknown'),
        ]);

        $this->span->setStatus(StatusCode::STATUS_ERROR, $throwable->getMessage());
        $this->span->recordException($throwable);
    }

    public function end(): void
    {
        $this->span->end();
        $this->scope->detach();
    }

    public function setAttributes(iterable $attributes): void
    {
        $this->span->setAttributes($attributes);
    }

    public function setStatus(string $code, ?string $description = null): void
    {
        $this->span->setStatus($code, $description);
    }

    public function setAttribute(string $key, null | array | bool | float | int | string $value): void
    {
        $this->span->setAttribute($key, $value);
    }

    public function detach(): void
    {
        $this->scope->detach();
    }

    public function getContext(): ContextInterface
    {
        return $this->context;
    }
}
