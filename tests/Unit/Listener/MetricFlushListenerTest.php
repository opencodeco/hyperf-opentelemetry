<?php

declare(strict_types=1);

namespace Tests\Unit\Listener;

use Hyperf\Command\Event\BeforeHandle;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Coordinator\Timer;
use Hyperf\Framework\Event\BeforeWorkerStart;
use Hyperf\OpenTelemetry\Listener\MetricFlushListener;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use PHPUnit\Framework\TestCase;
use Swoole\Server;

/**
 * @internal
 */
class MetricFlushListenerTest extends TestCase
{
    private ConfigInterface $config;

    private ContainerInterface $container;

    private Timer $timer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(ConfigInterface::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->timer = $this->createMock(Timer::class);
        $this->meterProvider = $this->createMock(MeterProviderInterface::class);

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
        $listener = new MetricFlushListener($this->container, $this->config, $this->meterProvider);

        $this->assertEquals([BeforeWorkerStart::class, BeforeHandle::class], $listener->listen());
    }

    public function testProcessWithValidWorkerIdSetsUpTimer(): void
    {
        $event = new BeforeWorkerStart($this->createMock(Server::class), 0);

        $this->config->expects($this->once())
            ->method('get')
            ->with('open-telemetry.exporter.metrics.flush_interval', 5)
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

        $this->meterProvider->expects($this->once())
            ->method('forceFlush');

        $listener = new MetricFlushListener($this->container, $this->config, $this->meterProvider);
        $listener->process($event);
    }

    public function testProcessWithoutMetricReaderInContainer(): void
    {
        $event = new BeforeWorkerStart($this->createMock(Server::class), 0);

        $this->config->expects($this->once())
            ->method('get')
            ->with('open-telemetry.exporter.metrics.flush_interval', 5)
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

        $this->meterProvider->expects($this->once())
            ->method('forceFlush');

        $listener = new MetricFlushListener($this->container, $this->config, $this->meterProvider);
        $listener->process($event);
        $this->assertTrue(true);
    }
}
