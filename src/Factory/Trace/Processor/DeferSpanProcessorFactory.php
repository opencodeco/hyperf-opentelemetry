<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Trace\Processor;

use Hyperf\OpenTelemetry\SpanProcessor\DeferSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;

class DeferSpanProcessorFactory implements TraceProcessorFactoryInterface
{
    public function make(SpanExporterInterface $exporter): SpanProcessorInterface
    {
        return new DeferSpanProcessor($exporter);
    }
}
