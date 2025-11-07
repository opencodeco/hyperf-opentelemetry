<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Trace;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\OpenTelemetry\Factory\Trace\Exporter\TraceExporterFactoryInterface;
use Hyperf\OpenTelemetry\Factory\Trace\Processor\TraceProcessorFactoryInterface;
use Hyperf\OpenTelemetry\Factory\Trace\Sampler\SamplerFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\NoopTracerProvider;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

class TracerProviderFactory
{
    public function __construct(
        protected readonly ConfigInterface $config,
        protected readonly ContainerInterface $container,
        protected readonly ResourceInfo $resource,
    ) {
    }

    public function __invoke(ContainerInterface $container): TracerProviderInterface
    {
        $tracesEnabled = $this->config->get('open-telemetry.traces.enabled', false);

        if (! $tracesEnabled) {
            return new NoopTracerProvider();
        }

        $exporter = $this->getExporter();
        $processor = $this->getProcessor($exporter);
        $sampler = $this->getSampler();

        return TracerProvider::builder()
            ->addSpanProcessor($processor)
            ->setResource($this->resource)
            ->setSampler($sampler)
            ->build();
    }

    public function getExporter(): SpanExporterInterface
    {
        $name = $this->config->get('open-telemetry.traces.exporter', 'otlp_http');

        /**
         * @var TraceExporterFactoryInterface $driver
         */
        $driver = $this->container->get(
            $this->config->get("open-telemetry.traces.exporters.{$name}.driver")
        );

        return $driver->make();
    }

    public function getProcessor(SpanExporterInterface $exporter): SpanProcessorInterface
    {
        $name = $this->config->get('open-telemetry.traces.processor', 'batch');

        /**
         * @var TraceProcessorFactoryInterface $driver
         */
        $driver = $this->container->get(
            $this->config->get("open-telemetry.traces.processors.{$name}.driver")
        );

        return $driver->make($exporter);
    }

    public function getSampler(): SamplerInterface
    {
        $name = $this->config->get('open-telemetry.traces.sampler', 'always_on');

        /**
         * @var SamplerFactory $driver
         */
        $driver = $this->container->get(
            $this->config->get("open-telemetry.traces.samplers.{$name}.driver")
        );

        return $driver->make();
    }
}
