<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Trace\Exporter;

use OpenTelemetry\SDK\Trace\SpanExporterInterface;

interface TraceExporterFactoryInterface
{
    public function make(): SpanExporterInterface;
}
