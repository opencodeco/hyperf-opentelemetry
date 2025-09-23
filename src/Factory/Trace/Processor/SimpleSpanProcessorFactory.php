<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Trace\Processor;

use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;

class SimpleSpanProcessorFactory implements TraceProcessorFactoryInterface
{
    public function make(SpanExporterInterface $exporter): SpanProcessorInterface
    {
        return new SimpleSpanProcessor($exporter);
    }
}
