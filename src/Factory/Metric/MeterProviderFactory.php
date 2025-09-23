<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Metric;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Metrics\MetricReaderInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use Hyperf\OpenTelemetry\Factory\Metric\Exporter\MetricExporterFactoryInterface;

class MeterProviderFactory
{
    public function __construct(
        protected readonly ConfigInterface $config,
        protected readonly ContainerInterface $container,
        protected readonly ResourceInfo $resource,
    ) {
    }

    public function getMeterProvider(): MeterProviderInterface
    {
        $exporter = $this->getExporter();

        $meterReader = new ExportingReader($exporter);

        $this->container->set(MetricReaderInterface::class, $meterReader);

        return MeterProvider::builder()
            ->setResource($this->resource)
            ->addReader($meterReader)
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
