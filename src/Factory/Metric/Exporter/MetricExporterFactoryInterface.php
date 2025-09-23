<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Metric\Exporter;

use OpenTelemetry\SDK\Metrics\MetricExporterInterface;

interface MetricExporterFactoryInterface
{
    public function make(): MetricExporterInterface;
}
