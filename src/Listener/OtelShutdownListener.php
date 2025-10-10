<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Listener;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\OnWorkerExit;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use Throwable;

class OtelShutdownListener implements ListenerInterface
{
    public function __construct(
        protected readonly MeterProviderInterface $meterProvider,
        protected readonly TracerProviderInterface $tracerProvider,
        protected readonly StdoutLoggerInterface $logger,
    ) {
    }

    public function listen(): array
    {
        return [
            OnWorkerExit::class,
        ];
    }

    public function process(object $event): void
    {
        try {
            $this->tracerProvider->shutdown();
        } catch (Throwable $e) {
            $this->logger->warning(sprintf('[OTel] tracer shutdown failed: %s', $e->getMessage()));
        }

        try {
            $this->meterProvider->shutdown();
        } catch (Throwable $e) {
            $this->logger->warning(sprintf('[OTel] meter shutdown failed: %s', $e->getMessage()));
        }
    }
}
