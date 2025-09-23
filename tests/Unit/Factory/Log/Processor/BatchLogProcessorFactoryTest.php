<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Log\Processor;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\LogRecordProcessorInterface;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Factory\Log\Processor\BatchLogProcessorFactory;

/**
 * @internal
 */
class BatchLogProcessorFactoryTest extends TestCase
{
    public function testMakeWithDefaultOptions()
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.logs.processors.batch.options', [])
            ->willReturn([]);

        $exporter = $this->createMock(LogRecordExporterInterface::class);
        $factory = new BatchLogProcessorFactory($config);
        $processor = $factory->make($exporter);

        $this->assertInstanceOf(LogRecordProcessorInterface::class, $processor);
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
            ->with('open-telemetry.logs.processors.batch.options', [])
            ->willReturn($options);

        $exporter = $this->createMock(LogRecordExporterInterface::class);
        $factory = new BatchLogProcessorFactory($config);
        $processor = $factory->make($exporter);

        $this->assertInstanceOf(LogRecordProcessorInterface::class, $processor);
    }
}
