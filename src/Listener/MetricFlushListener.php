<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Coordinator\Timer;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeWorkerStart;
use OpenTelemetry\SDK\Metrics\MetricReaderInterface;

class MetricFlushListener implements ListenerInterface
{
    private Timer $timer;

    public function __construct(
        protected readonly ContainerInterface $container,
        protected readonly ConfigInterface $config
    ) {
        $this->timer = $this->container->make(Timer::class);
    }

    public function listen(): array
    {
        return [
            BeforeWorkerStart::class,
        ];
    }

    public function process(object $event): void
    {
        if ($event->workerId === null) {
            return;
        }

        $timerInterval = (int) $this->config->get(
            'open-telemetry.exporter.metrics.flush_interval',
            5
        );

        $timerId = $this->timer->tick($timerInterval, function (): void {
            if ($this->container->has(MetricReaderInterface::class)) {
                $metricReader = $this->container->get(MetricReaderInterface::class);
                $metricReader->collect();
            }
        });

        Coroutine::create(function () use ($timerId): void {
            CoordinatorManager::until(Constants::WORKER_EXIT)->yield();
            $this->timer->clear($timerId);
        });
    }
}
