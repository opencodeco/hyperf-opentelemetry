<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Trace\Exporter;

use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporterFactory;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;

class StdoutTraceExporterFactory implements TraceExporterFactoryInterface
{
    public function make(): SpanExporterInterface
    {
        return (new ConsoleSpanExporterFactory())->create();
    }
}
