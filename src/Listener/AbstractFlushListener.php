<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Listener;

use Hyperf\Command\Event\AfterHandle;
use Hyperf\Command\Event\BeforeHandle;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Coordinator\Timer;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeWorkerStart;
use Throwable;

abstract class AbstractFlushListener implements ListenerInterface
{
    private Timer $timer;

    private ?int $timerId = null;

    private bool $running = false;

    public function __construct(
        protected readonly ContainerInterface $container,
        protected readonly ConfigInterface $config,
        protected readonly StdoutLoggerInterface $logger,
    ) {
        $this->timer = $this->container->make(Timer::class);
    }

    public function listen(): array
    {
        return [
            BeforeWorkerStart::class,
            BeforeHandle::class,
            AfterHandle::class,
        ];
    }

    abstract public function flush(): void;

    abstract public function exportInterval(): float;

    public function process(object $event): void
    {
        if ($event instanceof BeforeWorkerStart || $event instanceof BeforeHandle) {
            $this->startTimer();
            return;
        }

        if ($event instanceof AfterHandle) {
            $this->clearTimer();
        }
    }

    private function startTimer(): void
    {
        if ($this->timerId !== null) {
            return;
        }

        $this->timerId = $this->timer->tick($this->exportInterval(), function (): void {
            if ($this->running) {
                return;
            }

            $this->running = true;

            try {
                $this->flush();
            } catch (Throwable $e) {
                $this->logger->warning('[OTel] periodic flush failed', ['exception' => $e]);
            } finally {
                $this->running = false;
            }
        });

        Coroutine::create(function (): void {
            CoordinatorManager::until(Constants::WORKER_EXIT)->yield();
            $this->clearTimer();
        });
    }

    private function clearTimer(): void
    {
        if ($this->timerId !== null) {
            $this->timer->clear($this->timerId);
            $this->timerId = null;
        }
    }
}
