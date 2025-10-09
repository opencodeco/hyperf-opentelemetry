<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Metric;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Metrics\MetricReaderInterface;
use Hyperf\OpenTelemetry\Factory\Metric\Exporter\MetricExporterFactoryInterface;

class MeterReaderFactory
{
    public function __construct(
        protected readonly ConfigInterface $config,
        protected readonly ContainerInterface $container,
    ) {
    }

    public function __invoke(ContainerInterface $container): MetricReaderInterface
    {
        $exporter = $this->getExporter();

        return new ExportingReader($exporter);
    }

    public function getExporter(): MetricExporterInterface
    {
        $name = $this->config->get('open-telemetry.metrics.exporter', 'otlp_http');

        /**
         * @var MetricExporterFactoryInterface $driver
         */
        $driver = $this->container->get(
            $this->config->get("open-telemetry.metrics.exporters.{$name}.driver")
        );

        return $driver->make();
    }
}
