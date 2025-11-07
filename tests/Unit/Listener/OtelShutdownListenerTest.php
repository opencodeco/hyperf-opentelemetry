<?php

declare(strict_types=1);

namespace Tests\Unit\Listener;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\OnWorkerExit;
use Hyperf\OpenTelemetry\Listener\OtelShutdownListener;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Swoole\Server;

/**
 * @internal
 */
class OtelShutdownListenerTest extends TestCase
{
    public function testImplementsListenerInterface(): void
    {
        $listener = new OtelShutdownListener(
            $this->createMock(MeterProviderInterface::class),
            $this->createMock(TracerProviderInterface::class),
            $this->createMock(StdoutLoggerInterface::class)
        );
        $this->assertInstanceOf(ListenerInterface::class, $listener);
    }

    public function testProcessCallsShutdown(): void
    {
        $meterProvider = $this->createMock(MeterProviderInterface::class);
        $tracerProvider = $this->createMock(TracerProviderInterface::class);
        $logger = $this->createMock(StdoutLoggerInterface::class);

        $meterProvider->expects($this->once())->method('shutdown');
        $tracerProvider->expects($this->once())->method('shutdown');
        $logger->expects($this->never())->method('warning');

        $listener = new OtelShutdownListener($meterProvider, $tracerProvider, $logger);
        $listener->process(new OnWorkerExit($this->createMock(Server::class), 0));
    }

    public function testProcessLogsWarningOnException(): void
    {
        $meterProvider = $this->createMock(MeterProviderInterface::class);
        $tracerProvider = $this->createMock(TracerProviderInterface::class);
        $logger = $this->createMock(StdoutLoggerInterface::class);

        $meterProvider->method('shutdown')->willThrowException(new RuntimeException('meter error'));
        $logger->expects($this->once())->method('warning')->with($this->stringContains('meter error'));

        $listener = new OtelShutdownListener($meterProvider, $tracerProvider, $logger);
        $listener->process(new OnWorkerExit($this->createMock(Server::class), 0));
    }
}
