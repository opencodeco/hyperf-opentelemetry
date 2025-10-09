<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory;

use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Logs\NoopLoggerProvider;
use OpenTelemetry\SDK\Metrics\NoopMeterProvider;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\NoopTracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

class SDKBuilder
{
    public function __construct(
        protected LoggerProviderInterface $logProvider,
        protected TracerProviderInterface $tracerProvider,
        protected MeterProviderInterface $meterProvider,
    ) {
    }

    public function build(): void
    {
        $enabled = ! Sdk::isDisabled();

        $tracerProvider = $enabled ? $this->tracerProvider : new NoopTracerProvider();
        $meterProvider = $enabled ? $this->meterProvider : new NoopMeterProvider();
        $loggerProvider = $enabled ? $this->logProvider : new NoopLoggerProvider();

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setMeterProvider($meterProvider)
            ->setLoggerProvider($loggerProvider)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();
    }
}
