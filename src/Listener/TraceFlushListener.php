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
use Hyperf\Command\Event\BeforeHandle;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

class TraceFlushListener implements ListenerInterface
{
    private Timer $timer;

    public function __construct(
        protected readonly ContainerInterface $container,
        protected readonly ConfigInterface $config,
        protected readonly TracerProviderInterface $tracerProvider,
    ) {
        $this->timer = $this->container->make(Timer::class);
    }

    public function listen(): array
    {
        return [
            BeforeWorkerStart::class,
            BeforeHandle::class,
        ];
    }

    public function process(object $event): void
    {
        $timerInterval = (int) $this->config->get(
            'open-telemetry.exporter.metrics.flush_interval',
            5
        );

        $timerId = $this->timer->tick($timerInterval, function (): void {
            $this->tracerProvider->forceFlush();
        });

        Coroutine::create(function () use ($timerId): void {
            CoordinatorManager::until(Constants::WORKER_EXIT)->yield();
            $this->timer->clear($timerId);
        });
    }
}
