<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Trace\Processor;

use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;

interface TraceProcessorFactoryInterface
{
    public function make(SpanExporterInterface $exporter): SpanProcessorInterface;
}
