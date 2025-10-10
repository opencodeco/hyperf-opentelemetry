<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Log;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\LogRecordProcessorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Factory\Log\Exporter\LogExporterFactoryInterface;
use Hyperf\OpenTelemetry\Factory\Log\LoggerProviderFactory;
use Hyperf\OpenTelemetry\Factory\Log\Processor\LogProcessorFactoryInterface;

/**
 * @internal
 */
class LoggerProviderFactoryTest extends TestCase
{
    public function testGetLoggerProviderReturnsLoggerProviderInterface()
    {
        $config = $this->createMock(ConfigInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $resource = $this->createMock(ResourceInfo::class);

        $exporterFactory = $this->createMock(LogExporterFactoryInterface::class);
        $exporter = $this->createMock(LogRecordExporterInterface::class);
        $exporterFactory->method('make')->willReturn($exporter);

        $processorFactory = $this->createMock(LogProcessorFactoryInterface::class);
        $processor = $this->createMock(LogRecordProcessorInterface::class);
        $processorFactory->method('make')->willReturn($processor);

        $config->method('get')
            ->willReturnMap([
                ['open-telemetry.logs.exporter', 'stdout', 'stdout'],
                ['open-telemetry.logs.exporters.stdout.driver', null, 'exporter.driver'],
                ['open-telemetry.logs.processor', 'simple', 'simple'],
                ['open-telemetry.logs.processors.simple.driver', null, 'processor.driver'],
            ]);
        $container->method('get')
            ->willReturnMap([
                ['exporter.driver', $exporterFactory],
                ['processor.driver', $processorFactory],
            ]);

        $factory = new LoggerProviderFactory($config, $container, $resource);
        $loggerProvider = $factory($container);
        $this->assertInstanceOf(LoggerProviderInterface::class, $loggerProvider);
    }

    public function testGetExporterReturnsExporter()
    {
        $config = $this->createMock(ConfigInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $resource = $this->createMock(ResourceInfo::class);

        $exporterFactory = $this->createMock(LogExporterFactoryInterface::class);
        $exporter = $this->createMock(LogRecordExporterInterface::class);
        $exporterFactory->method('make')->willReturn($exporter);

        $config->method('get')
            ->willReturnMap([
                ['open-telemetry.logs.exporter', 'stdout', 'stdout'],
                ['open-telemetry.logs.exporters.stdout.driver', null, 'exporter.driver'],
            ]);
        $container->method('get')
            ->with('exporter.driver')
            ->willReturn($exporterFactory);

        $factory = new LoggerProviderFactory($config, $container, $resource);
        $result = $factory->getExporter();
        $this->assertInstanceOf(LogRecordExporterInterface::class, $result);
    }

    public function testGetProcessorReturnsProcessor()
    {
        $config = $this->createMock(ConfigInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $resource = $this->createMock(ResourceInfo::class);

        $processorFactory = $this->createMock(LogProcessorFactoryInterface::class);
        $processor = $this->createMock(LogRecordProcessorInterface::class);
        $processorFactory->method('make')->willReturn($processor);

        $config->method('get')
            ->willReturnMap([
                ['open-telemetry.logs.processor', 'simple', 'simple'],
                ['open-telemetry.logs.processors.simple.driver', null, 'processor.driver'],
            ]);
        $container->method('get')
            ->with('processor.driver')
            ->willReturn($processorFactory);

        $factory = new LoggerProviderFactory($config, $container, $resource);
        $exporter = $this->createMock(LogRecordExporterInterface::class);
        $result = $factory->getProcessor($exporter);
        $this->assertInstanceOf(LogRecordProcessorInterface::class, $result);
    }
}
