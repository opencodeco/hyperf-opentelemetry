<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Trace\Processor;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Factory\Trace\Processor\BatchSpanProcessorFactory;

/**
 * @internal
 */
class BatchSpanProcessorFactoryTest extends TestCase
{
    public function testMakeWithDefaultOptions()
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.traces.processors.batch.options', [])
            ->willReturn([]);

        $exporter = $this->createMock(SpanExporterInterface::class);
        $meterProvider = $this->createMock(MeterProviderInterface::class);
        $factory = new BatchSpanProcessorFactory($config, $meterProvider);
        $processor = $factory->make($exporter);

        $this->assertInstanceOf(SpanProcessorInterface::class, $processor);
        $this->assertInstanceOf(BatchSpanProcessor::class, $processor);
    }

    public function testMakeWithCustomOptions()
    {
        $options = [
            'max_queue_size' => 1000,
            'schedule_delay_ms' => 5000,
            'export_timeout_ms' => 30000,
            'max_export_batch_size' => 512,
            'auto_flush' => false,
        ];
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.traces.processors.batch.options', [])
            ->willReturn($options);

        $exporter = $this->createMock(SpanExporterInterface::class);
        $meterProvider = $this->createMock(MeterProviderInterface::class);
        $factory = new BatchSpanProcessorFactory($config, $meterProvider);
        $processor = $factory->make($exporter);

        $this->assertInstanceOf(SpanProcessorInterface::class, $processor);
        $this->assertInstanceOf(BatchSpanProcessor::class, $processor);
    }
}
