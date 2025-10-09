<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Metric;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricReaderInterface;
use OpenTelemetry\SDK\Metrics\NoopMeterProvider;
use OpenTelemetry\SDK\Resource\ResourceInfo;

class MeterProviderFactory
{
    public function __construct(
        protected readonly ConfigInterface $config,
        protected readonly ResourceInfo $resource,
        protected readonly MetricReaderInterface $metricReader,
    ) {
    }

    public function __invoke(ContainerInterface $container): MeterProviderInterface
    {
        $metricsEnabled = $this->config->get('open-telemetry.metrics.enabled', false);

        if (! $metricsEnabled) {
            return new NoopMeterProvider();
        }

        return MeterProvider::builder()
            ->setResource($this->resource)
            ->addReader($this->metricReader)
            ->build();
    }
}
