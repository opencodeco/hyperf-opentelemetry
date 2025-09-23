<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\SDK\Logs\NoopLoggerProvider;
use OpenTelemetry\SDK\Metrics\NoopMeterProvider;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\NoopTracerProvider;
use Hyperf\OpenTelemetry\Factory\Log\LoggerProviderFactory;
use Hyperf\OpenTelemetry\Factory\Metric\MeterProviderFactory;
use Hyperf\OpenTelemetry\Factory\Trace\TracerProviderFactory;

class SDKBuilder
{
    public function __construct(
        protected ConfigInterface $config,
        protected LoggerProviderFactory $logProviderFactory,
        protected TracerProviderFactory $tracerProviderFactory,
        protected MeterProviderFactory $meterProviderFactory,
    ) {
    }

    public function build(): void
    {
        $traces = $this->config->get('open-telemetry.traces.enabled', false);
        $metrics = $this->config->get('open-telemetry.metrics.enabled', false);
        $logs = $this->config->get('open-telemetry.logs.enabled', false);

        $enabled = ! Sdk::isDisabled();

        $tracerProvider = ($traces && $enabled)
            ? $this->tracerProviderFactory->getTracerProvider()
            : new NoopTracerProvider();

        $meterProvider = ($metrics && $enabled)
            ? $this->meterProviderFactory->getMeterProvider()
            : new NoopMeterProvider();

        $loggerProvider = ($logs && $enabled)
            ? $this->logProviderFactory->getLoggerProvider()
            : new NoopLoggerProvider();

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setMeterProvider($meterProvider)
            ->setLoggerProvider($loggerProvider)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();
    }
}
