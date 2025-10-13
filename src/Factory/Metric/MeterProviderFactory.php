<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Metric;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\OpenTelemetry\Factory\Metric\Exporter\MetricExporterFactoryInterface;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Metrics\NoopMeterProvider;
use OpenTelemetry\SDK\Resource\ResourceInfo;

class MeterProviderFactory
{
    public function __construct(
        protected readonly ConfigInterface $config,
        protected readonly ContainerInterface $container,
        protected readonly ResourceInfo $resource,
    ) {
    }

    public function __invoke(ContainerInterface $container): MeterProviderInterface
    {
        $metricsEnabled = $this->config->get('open-telemetry.metrics.enabled', false);

        if (! $metricsEnabled) {
            return new NoopMeterProvider();
        }

        $reader = new ExportingReader($this->getExporter());

        return MeterProvider::builder()
            ->setResource($this->resource)
            ->addReader($reader)
            ->build();
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
