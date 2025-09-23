<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use Hyperf\OpenTelemetry\Support\SpanScope;

class Instrumentation
{
    public function __construct(
        private readonly CachedInstrumentation $instrumentation
    ) {
    }

    public function meter(): MeterInterface
    {
        return $this->instrumentation->meter();
    }

    public function tracer(): TracerInterface
    {
        return $this->instrumentation->tracer();
    }

    public function propagator(): TextMapPropagatorInterface
    {
        return Globals::propagator();
    }

    public function startSpan(
        string $name,
        int $spanKind,
        array $attributes = [],
        ?int $startTimestamp = 0,
        ?ContextInterface $explicitContext = null
    ): SpanScope {
        $parent = $explicitContext ?? Context::getCurrent();

        $span = $this->tracer()
            ->spanBuilder($name)
            ->setSpanKind($spanKind)
            ->setParent($parent)
            ->setStartTimestamp($startTimestamp)
            ->setAttributes($attributes)
            ->startSpan();

        $context = $span->storeInContext($parent);
        $scope = $context->activate();

        return new SpanScope($span, $scope, $context);
    }
}
