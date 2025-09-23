<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Metric;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use OpenTelemetry\SDK\Metrics\MetricReaderInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Factory\Metric\Exporter\MetricExporterFactoryInterface;
use Hyperf\OpenTelemetry\Factory\Metric\MeterProviderFactory;

/**
 * @internal
 */
class MeterProviderFactoryTest extends TestCase
{
    public function testGetMeterProviderReturnsMeterProviderInterface()
    {
        $config = $this->createMock(ConfigInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $resource = $this->createMock(ResourceInfo::class);

        $exporterFactory = $this->createMock(MetricExporterFactoryInterface::class);
        $exporter = $this->createMock(MetricExporterInterface::class);
        $exporterFactory->method('make')->willReturn($exporter);

        $config->method('get')
            ->willReturnMap([
                ['open-telemetry.metrics.exporter', 'otlp_http', 'otlp_http'],
                ['open-telemetry.metrics.exporters.otlp_http.driver', null, 'exporter.driver'],
            ]);
        $container->method('get')
            ->with('exporter.driver')
            ->willReturn($exporterFactory);
        $container->expects($this->once())
            ->method('set')
            ->with(MetricReaderInterface::class, $this->isInstanceOf(MetricReaderInterface::class));

        $factory = new MeterProviderFactory($config, $container, $resource);
        $meterProvider = $factory->getMeterProvider();
        $this->assertInstanceOf(MeterProviderInterface::class, $meterProvider);
    }

    public function testGetExporterReturnsMetricExporterInterface()
    {
        $config = $this->createMock(ConfigInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $resource = $this->createMock(ResourceInfo::class);

        $exporterFactory = $this->createMock(MetricExporterFactoryInterface::class);
        $exporter = $this->createMock(MetricExporterInterface::class);
        $exporterFactory->method('make')->willReturn($exporter);

        $config->method('get')
            ->willReturnMap([
                ['open-telemetry.metrics.exporter', 'otlp_http', 'otlp_http'],
                ['open-telemetry.metrics.exporters.otlp_http.driver', null, 'exporter.driver'],
            ]);
        $container->method('get')
            ->with('exporter.driver')
            ->willReturn($exporterFactory);

        $factory = new MeterProviderFactory($config, $container, $resource);
        $result = $factory->getExporter();
        $this->assertInstanceOf(MetricExporterInterface::class, $result);
    }
}
