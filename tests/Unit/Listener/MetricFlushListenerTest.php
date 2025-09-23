<?php

declare(strict_types=1);

namespace Tests\Unit\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Coordinator\Timer;
use Hyperf\Framework\Event\BeforeWorkerStart;
use OpenTelemetry\SDK\Metrics\MetricReaderInterface;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Listener\MetricFlushListener;
use stdClass;
use Swoole\Server;

/**
 * @internal
 */
class MetricFlushListenerTest extends TestCase
{
    private ConfigInterface $config;

    private ContainerInterface $container;

    private MetricReaderInterface $metricReader;

    private Timer $timer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(ConfigInterface::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->metricReader = $this->createMock(MetricReaderInterface::class);
        $this->timer = $this->createMock(Timer::class);

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
        $listener = new MetricFlushListener($this->container, $this->config);

        $this->assertEquals([BeforeWorkerStart::class], $listener->listen());
    }

    public function testProcessWithNullWorkerIdShouldDoNothing(): void
    {
        $event = new stdClass();
        $event->workerId = null;

        $this->config->expects($this->never())->method('get');
        $this->container->expects($this->never())->method('has');

        $listener = new MetricFlushListener($this->container, $this->config);
        $listener->process($event);
    }

    public function testProcessWithValidWorkerIdSetsUpTimer(): void
    {
        $event = new BeforeWorkerStart($this->createMock(Server::class), 0);

        $this->config->expects($this->once())
            ->method('get')
            ->with('open-telemetry.exporter.metrics.flush_interval', 5)
            ->willReturn(10);

        $this->container->expects($this->once())
            ->method('has')
            ->with(MetricReaderInterface::class)
            ->willReturn(true);

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

        $this->container->expects($this->once())
            ->method('get')
            ->with(MetricReaderInterface::class)
            ->willReturn($this->metricReader);

        $this->metricReader->expects($this->once())
            ->method('collect');

        $listener = new MetricFlushListener($this->container, $this->config);
        $listener->process($event);
    }

    public function testProcessWithoutMetricReaderInContainer(): void
    {
        $event = new BeforeWorkerStart($this->createMock(Server::class), 0);

        $this->config->expects($this->once())
            ->method('get')
            ->with('open-telemetry.exporter.metrics.flush_interval', 5)
            ->willReturn(10);

        $this->container->expects($this->once())
            ->method('has')
            ->with(MetricReaderInterface::class)
            ->willReturn(false);

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

        $this->container->expects($this->never())
            ->method('get')
            ->with(MetricReaderInterface::class);

        $listener = new MetricFlushListener($this->container, $this->config);
        $listener->process($event);
    }
}
