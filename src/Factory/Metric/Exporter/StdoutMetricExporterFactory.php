<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Metric\Exporter;

use OpenTelemetry\SDK\Metrics\MetricExporter\ConsoleMetricExporterFactory;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;

class StdoutMetricExporterFactory implements MetricExporterFactoryInterface
{
    public function make(): MetricExporterInterface
    {
        return (new ConsoleMetricExporterFactory())->create();
    }
}
