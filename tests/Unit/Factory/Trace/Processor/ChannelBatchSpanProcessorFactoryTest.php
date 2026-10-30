<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Trace\Processor;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Factory\Trace\Processor\ChannelBatchSpanProcessorFactory;
use Hyperf\OpenTelemetry\SpanProcessor\ChannelBatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ChannelBatchSpanProcessorFactoryTest extends TestCase
{
    public function testMakeWithDefaultOptions(): void
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.traces.processors.channel.options', [])
            ->willReturn([]);

        $exporter = $this->createMock(SpanExporterInterface::class);
        $factory = new ChannelBatchSpanProcessorFactory($config);
        $processor = $factory->make($exporter);

        $this->assertInstanceOf(SpanProcessorInterface::class, $processor);
        $this->assertInstanceOf(ChannelBatchSpanProcessor::class, $processor);
    }

    public function testMakeWithCustomOptions(): void
    {
        $options = [
            'max_batch_size' => 256,
            'channel_capacity' => 64,
            'flush_interval' => 2.5,
        ];
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.traces.processors.channel.options', [])
            ->willReturn($options);

        $exporter = $this->createMock(SpanExporterInterface::class);
        $factory = new ChannelBatchSpanProcessorFactory($config);
        $processor = $factory->make($exporter);

        $this->assertInstanceOf(SpanProcessorInterface::class, $processor);
        $this->assertInstanceOf(ChannelBatchSpanProcessor::class, $processor);
    }
}
