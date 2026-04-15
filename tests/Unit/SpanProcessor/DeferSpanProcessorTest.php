<?php

declare(strict_types=1);

namespace Tests\Unit\SpanProcessor;

use Hyperf\Context\Context;
use Hyperf\OpenTelemetry\SpanProcessor\DeferSpanProcessor;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Trace\ReadableSpanInterface;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 */
class DeferSpanProcessorTest extends TestCase
{
    protected function tearDown(): void
    {
        Context::destroy(ServerRequestInterface::class);
        Context::destroy('otel.defer.spans');
    }

    public function testOnEndExportsImmediatelyWithoutHttpContext(): void
    {
        $spanData = $this->createMock(SpanDataInterface::class);

        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->expects($this->once())
            ->method('export')
            ->with([$spanData])
            ->willReturn(new CompletedFuture(true));

        $span = $this->createSampledSpan($spanData);

        $processor = new DeferSpanProcessor($exporter);
        $processor->onEnd($span);
    }

    public function testOnEndBuffersSpansInHttpContext(): void
    {
        Context::set(ServerRequestInterface::class, $this->createMock(ServerRequestInterface::class));

        $spanData1 = $this->createMock(SpanDataInterface::class);
        $spanData2 = $this->createMock(SpanDataInterface::class);

        $exported = [];
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->method('export')
            ->willReturnCallback(function (array $spans) use (&$exported) {
                $exported = $spans;
                return new CompletedFuture(true);
            });

        $processor = new DeferSpanProcessor($exporter);
        $processor->onEnd($this->createSampledSpan($spanData1));
        $processor->onEnd($this->createSampledSpan($spanData2));

        // Verify spans are buffered in context (not yet exported)
        $buffered = Context::get('otel.defer.spans', []);
        $this->assertCount(2, $buffered);
        $this->assertSame($spanData1, $buffered[0]);
        $this->assertSame($spanData2, $buffered[1]);
    }

    public function testOnEndSkipsNonSampledSpans(): void
    {
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->expects($this->never())->method('export');

        $spanContext = $this->createMock(SpanContextInterface::class);
        $spanContext->method('isSampled')->willReturn(false);

        $span = $this->createMock(ReadableSpanInterface::class);
        $span->method('getContext')->willReturn($spanContext);

        $processor = new DeferSpanProcessor($exporter);
        $processor->onEnd($span);
    }

    public function testOnEndIsNoOpAfterShutdown(): void
    {
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->expects($this->once())->method('shutdown')->willReturn(true);
        $exporter->expects($this->never())->method('export');

        $processor = new DeferSpanProcessor($exporter);
        $processor->shutdown();

        $spanData = $this->createMock(SpanDataInterface::class);
        $processor->onEnd($this->createSampledSpan($spanData));
    }

    public function testShutdownDelegatesToExporter(): void
    {
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->expects($this->once())->method('shutdown')->willReturn(true);

        $processor = new DeferSpanProcessor($exporter);
        $this->assertTrue($processor->shutdown());
    }

    public function testShutdownReturnsFalseWhenAlreadyClosed(): void
    {
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->expects($this->once())->method('shutdown')->willReturn(true);

        $processor = new DeferSpanProcessor($exporter);
        $processor->shutdown();
        $this->assertFalse($processor->shutdown());
    }

    public function testForceFlushDelegatesToExporter(): void
    {
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->expects($this->once())->method('forceFlush')->willReturn(true);

        $processor = new DeferSpanProcessor($exporter);
        $this->assertTrue($processor->forceFlush());
    }

    public function testForceFlushReturnsFalseWhenClosed(): void
    {
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->method('shutdown')->willReturn(true);
        $exporter->expects($this->never())->method('forceFlush');

        $processor = new DeferSpanProcessor($exporter);
        $processor->shutdown();
        $this->assertFalse($processor->forceFlush());
    }

    public function testDeferredExportFiresWhenCoroutineEnds(): void
    {
        $exported = [];
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporter->method('export')
            ->willReturnCallback(function (array $spans) use (&$exported) {
                $exported = $spans;
                return new CompletedFuture(true);
            });

        $processor = new DeferSpanProcessor($exporter);
        $spanData = $this->createMock(SpanDataInterface::class);

        $channel = new \Swoole\Coroutine\Channel(1);

        \Hyperf\Coroutine\Coroutine::create(function () use ($processor, $spanData, $channel): void {
            Context::set(ServerRequestInterface::class, $this->createMock(ServerRequestInterface::class));
            $processor->onEnd($this->createSampledSpan($spanData));
            $channel->push(true);
        });

        // Wait for sub-coroutine + its defer to complete
        $channel->pop(1.0);
        // Small yield to allow defer to execute
        \Swoole\Coroutine::sleep(0.01);

        $this->assertCount(1, $exported);
        $this->assertSame($spanData, $exported[0]);
    }

    private function createSampledSpan(SpanDataInterface $spanData): ReadableSpanInterface
    {
        $spanContext = $this->createMock(SpanContextInterface::class);
        $spanContext->method('isSampled')->willReturn(true);

        $span = $this->createMock(ReadableSpanInterface::class);
        $span->method('getContext')->willReturn($spanContext);
        $span->method('toSpanData')->willReturn($spanData);

        return $span;
    }
}
