<?php

declare(strict_types=1);

namespace Tests\Unit\Listener;

use Hyperf\Command\Event\AfterHandle;
use Hyperf\Command\Event\BeforeHandle;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Coordinator\Timer;
use Hyperf\Framework\Event\BeforeWorkerStart;
use Hyperf\OpenTelemetry\Listener\MetricFlushListener;
use Hyperf\OpenTelemetry\Listener\TraceFlushListener;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use PHPUnit\Framework\TestCase;
use Swoole\Server;

/**
 * @internal
 */
class TraceFlushListenerTest extends TestCase
{
    private ConfigInterface $config;

    private ContainerInterface $container;

    private StdoutLoggerInterface $logger;

    private TracerProviderInterface $tracerProvider;

    private Timer $timer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(ConfigInterface::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->timer = $this->createMock(Timer::class);
        $this->logger = $this->createMock(StdoutLoggerInterface::class);
        $this->tracerProvider = $this->createMock(TracerProviderInterface::class);

        $this->container->expects($this->once())
            ->method('make')
            ->with(Timer::class)
            ->willReturn($this->timer);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        CoordinatorManager::until(Constants::WORKER_EXIT)->resume();
    }

    public function testListenReturnsCorrectEvents(): void
    {
        $listener = new TraceFlushListener($this->container, $this->config, $this->logger, $this->tracerProvider);
        $this->assertEquals([
            BeforeWorkerStart::class,
            BeforeHandle::class,
            AfterHandle::class,
        ], $listener->listen());
    }

    public function testProcessWithValidWorkerIdSetsUpTimer(): void
    {
        $event = new BeforeWorkerStart($this->createMock(Server::class), 0);

        $this->config->expects($this->once())
            ->method('get')
            ->with('open-telemetry.traces.export_interval', 5)
            ->willReturn(10);

        $this->timer->expects($this->once())
            ->method('tick')
            ->with(
                10,
                $this->callback(function ($closure) {
                    $this->assertIsCallable($closure);
                    $closure();
                    return true;
                })
            );

        $this->tracerProvider->expects($this->once())
            ->method('forceFlush');

        $listener = new TraceFlushListener($this->container, $this->config, $this->logger, $this->tracerProvider);
        $listener->process($event);
        $this->assertInstanceOf(TraceFlushListener::class, $listener);
    }

    public function testProcessWithoutTracerReaderInContainer(): void
    {
        $event = new BeforeWorkerStart($this->createMock(Server::class), 0);

        $this->config->expects($this->once())
            ->method('get')
            ->with('open-telemetry.traces.export_interval', 5)
            ->willReturn(10);

        $this->timer->expects($this->once())
            ->method('tick')
            ->with(
                10,
                $this->callback(function ($closure) {
                    $this->assertIsCallable($closure);
                    $closure();
                    return true;
                })
            );

        $this->tracerProvider->expects($this->once())
            ->method('forceFlush');

        $listener = new TraceFlushListener($this->container, $this->config, $this->logger, $this->tracerProvider);
        $listener->process($event);
        $this->assertInstanceOf(TraceFlushListener::class, $listener);
    }
}
