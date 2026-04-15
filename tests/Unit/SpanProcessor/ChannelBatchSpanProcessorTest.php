<?php

declare(strict_types=1);

namespace Tests\Unit\SpanProcessor;

use Hyperf\OpenTelemetry\SpanProcessor\ChannelBatchSpanProcessor;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Trace\ReadableSpanInterface;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ChannelBatchSpanProcessorTest extends TestCase
{
    public function testOnEndAccumulatesSpansUntilBatchSize(): void
    {
        $exported = [];
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->method('export')
            ->willReturnCallback(function (array $spans) use (&$exported) {
                $exported = array_merge($exported, $spans);
                return new CompletedFuture(true);
            });

        // maxBatchSize=3 so we can test accumulation and flush
        $processor = new ChannelBatchSpanProcessor(
            exporter: $exporter,
            maxBatchSize: 3,
            channelCapacity: 10,
            flushInterval: 60.0, // long interval so timer doesn't interfere
        );

        // Send 2 spans — should NOT export yet (batch not full)
        $processor->onEnd($this->createSampledSpan());
        $processor->onEnd($this->createSampledSpan());

        // Give consumer time to process (should have nothing to process)
        \Swoole\Coroutine::sleep(0.05);
        $this->assertCount(0, $exported);

        // Send 3rd span — batch full, should push to channel
        $processor->onEnd($this->createSampledSpan());

        // Give consumer coroutine time to process
        \Swoole\Coroutine::sleep(0.05);
        $this->assertCount(3, $exported);

        $processor->shutdown();
    }

    public function testOnEndSkipsNonSampledSpans(): void
    {
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->expects($this->never())->method('export');

        $processor = new ChannelBatchSpanProcessor(
            exporter: $exporter,
            maxBatchSize: 1,
            channelCapacity: 10,
            flushInterval: 60.0,
        );

        $spanContext = $this->createMock(SpanContextInterface::class);
        $spanContext->method('isSampled')->willReturn(false);

        $span = $this->createMock(ReadableSpanInterface::class);
        $span->method('getContext')->willReturn($spanContext);

        $processor->onEnd($span);

        \Swoole\Coroutine::sleep(0.05);
        $processor->shutdown();
    }

    public function testOnEndIsNoOpAfterShutdown(): void
    {
        $exportCount = 0;
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->method('export')
            ->willReturnCallback(function () use (&$exportCount) {
                $exportCount++;
                return new CompletedFuture(true);
            });
        $exporter->method('shutdown')->willReturn(true);

        $processor = new ChannelBatchSpanProcessor(
            exporter: $exporter,
            maxBatchSize: 1,
            channelCapacity: 10,
            flushInterval: 60.0,
        );

        $processor->shutdown();

        // These should be no-ops
        $processor->onEnd($this->createSampledSpan());
        $processor->onEnd($this->createSampledSpan());

        \Swoole\Coroutine::sleep(0.05);
        $this->assertSame(0, $exportCount);
    }

    public function testShutdownDelegatesToExporter(): void
    {
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->expects($this->once())->method('shutdown')->willReturn(true);

        $processor = new ChannelBatchSpanProcessor(
            exporter: $exporter,
            maxBatchSize: 100,
            channelCapacity: 10,
            flushInterval: 60.0,
        );

        $this->assertTrue($processor->shutdown());
    }

    public function testShutdownReturnsFalseWhenAlreadyClosed(): void
    {
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->method('shutdown')->willReturn(true);

        $processor = new ChannelBatchSpanProcessor(
            exporter: $exporter,
            maxBatchSize: 100,
            channelCapacity: 10,
            flushInterval: 60.0,
        );

        $processor->shutdown();
        $this->assertFalse($processor->shutdown());
    }

    public function testForceFlushPushesPartialBatch(): void
    {
        $exported = [];
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->method('export')
            ->willReturnCallback(function (array $spans) use (&$exported) {
                $exported = array_merge($exported, $spans);
                return new CompletedFuture(true);
            });
        $exporter->method('forceFlush')->willReturn(true);

        $processor = new ChannelBatchSpanProcessor(
            exporter: $exporter,
            maxBatchSize: 100, // large so batch never fills naturally
            channelCapacity: 10,
            flushInterval: 60.0,
        );

        $processor->onEnd($this->createSampledSpan());
        $processor->onEnd($this->createSampledSpan());

        // forceFlush should push the partial batch
        $result = $processor->forceFlush();
        $this->assertTrue($result);

        // Give consumer time to process
        \Swoole\Coroutine::sleep(0.05);
        $this->assertCount(2, $exported);

        $processor->shutdown();
    }

    public function testForceFlushReturnsFalseWhenClosed(): void
    {
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->method('shutdown')->willReturn(true);

        $processor = new ChannelBatchSpanProcessor(
            exporter: $exporter,
            maxBatchSize: 100,
            channelCapacity: 10,
            flushInterval: 60.0,
        );

        $processor->shutdown();
        $this->assertFalse($processor->forceFlush());
    }

    public function testShutdownFlushesRemainingSpans(): void
    {
        $exported = [];
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->method('export')
            ->willReturnCallback(function (array $spans) use (&$exported) {
                $exported = array_merge($exported, $spans);
                return new CompletedFuture(true);
            });
        $exporter->method('shutdown')->willReturn(true);

        $processor = new ChannelBatchSpanProcessor(
            exporter: $exporter,
            maxBatchSize: 100,
            channelCapacity: 10,
            flushInterval: 60.0,
        );

        // Add spans but don't fill the batch
        $processor->onEnd($this->createSampledSpan());
        $processor->onEnd($this->createSampledSpan());

        // Shutdown should flush remaining
        $processor->shutdown();

        // Give consumer time to drain
        \Swoole\Coroutine::sleep(0.05);
        $this->assertCount(2, $exported);
    }

    public function testChannelFullDropsSpansGracefully(): void
    {
        $exporter = $this->createMock(SpanExporterInterface::class);
        // Exporter that blocks to simulate slow export
        $exporter->method('export')
            ->willReturnCallback(function () {
                \Swoole\Coroutine::sleep(1.0);
                return new CompletedFuture(true);
            });

        // Channel capacity=1, maxBatch=1 — one push fills the channel immediately
        $processor = new ChannelBatchSpanProcessor(
            exporter: $exporter,
            maxBatchSize: 1,
            channelCapacity: 1,
            flushInterval: 60.0,
        );

        // First span → pushes batch → channel accepts
        $processor->onEnd($this->createSampledSpan());
        \Swoole\Coroutine::sleep(0.01);

        // Second span → pushes batch → consumer is busy, channel full → should drop gracefully
        $processor->onEnd($this->createSampledSpan());

        // Third span → also dropped
        $processor->onEnd($this->createSampledSpan());

        // Should not crash or hang — just drops
        $this->assertTrue(true);

        $processor->shutdown();
    }

    private function createSampledSpan(): ReadableSpanInterface
    {
        $spanContext = $this->createMock(SpanContextInterface::class);
        $spanContext->method('isSampled')->willReturn(true);

        $spanData = $this->createMock(SpanDataInterface::class);

        $span = $this->createMock(ReadableSpanInterface::class);
        $span->method('getContext')->willReturn($spanContext);
        $span->method('toSpanData')->willReturn($spanData);

        return $span;
    }
}
