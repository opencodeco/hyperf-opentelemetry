<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Trace;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\OpenTelemetry\Factory\Trace\Exporter\TraceExporterFactoryInterface;
use Hyperf\OpenTelemetry\Factory\Trace\Processor\TraceProcessorFactoryInterface;
use Hyperf\OpenTelemetry\Factory\Trace\Sampler\SamplerFactory;
use Hyperf\OpenTelemetry\Factory\Trace\TracerProviderFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class TracerProviderFactoryTest extends TestCase
{
    public function testGetTracerProviderReturnsTracerProviderInterface()
    {
        $config = $this->createMock(ConfigInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $resource = $this->createMock(ResourceInfo::class);

        $exporterFactory = $this->createMock(TraceExporterFactoryInterface::class);
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporterFactory->method('make')->willReturn($exporter);

        $processorFactory = $this->createMock(TraceProcessorFactoryInterface::class);
        $processor = $this->createMock(SpanProcessorInterface::class);
        $processorFactory->method('make')->willReturn($processor);

        $samplerFactory = $this->createMock(SamplerFactory::class);
        $sampler = $this->createMock(SamplerInterface::class);
        $samplerFactory->method('make')->willReturn($sampler);

        $config->method('get')
            ->willReturnMap([
                ['open-telemetry.traces.exporter', 'otlp_http', 'otlp_http'],
                ['open-telemetry.traces.exporters.otlp_http.driver', null, 'exporter.driver'],
                ['open-telemetry.traces.processor', 'batch', 'batch'],
                ['open-telemetry.traces.processors.batch.driver', null, 'processor.driver'],
                ['open-telemetry.traces.sampler', 'always_on', 'always_on'],
                ['open-telemetry.traces.samplers.always_on.driver', null, 'sampler.driver'],
            ]);
        $container->method('get')
            ->willReturnMap([
                ['exporter.driver', $exporterFactory],
                ['processor.driver', $processorFactory],
                ['sampler.driver', $samplerFactory],
            ]);

        $factory = new TracerProviderFactory($config, $container, $resource);
        $tracerProvider = $factory($container);
        $this->assertInstanceOf(TracerProviderInterface::class, $tracerProvider);
    }

    public function testGetExporterReturnsSpanExporterInterface()
    {
        $config = $this->createMock(ConfigInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $resource = $this->createMock(ResourceInfo::class);

        $exporterFactory = $this->createMock(TraceExporterFactoryInterface::class);
        $exporter = $this->createMock(SpanExporterInterface::class);
        $exporterFactory->method('make')->willReturn($exporter);

        $config->method('get')
            ->willReturnMap([
                ['open-telemetry.traces.exporter', 'otlp_http', 'otlp_http'],
                ['open-telemetry.traces.exporters.otlp_http.driver', null, 'exporter.driver'],
            ]);
        $container->method('get')
            ->with('exporter.driver')
            ->willReturn($exporterFactory);

        $factory = new TracerProviderFactory($config, $container, $resource);
        $result = $factory->getExporter();
        $this->assertInstanceOf(SpanExporterInterface::class, $result);
    }

    public function testGetProcessorReturnsSpanProcessorInterface()
    {
        $config = $this->createMock(ConfigInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $resource = $this->createMock(ResourceInfo::class);

        $processorFactory = $this->createMock(TraceProcessorFactoryInterface::class);
        $processor = $this->createMock(SpanProcessorInterface::class);
        $processorFactory->method('make')->willReturn($processor);

        $config->method('get')
            ->willReturnMap([
                ['open-telemetry.traces.processor', 'batch', 'batch'],
                ['open-telemetry.traces.processors.batch.driver', null, 'processor.driver'],
            ]);
        $container->method('get')
            ->with('processor.driver')
            ->willReturn($processorFactory);

        $factory = new TracerProviderFactory($config, $container, $resource);
        $exporter = $this->createMock(SpanExporterInterface::class);
        $result = $factory->getProcessor($exporter);
        $this->assertInstanceOf(SpanProcessorInterface::class, $result);
    }

    public function testGetSamplerReturnsSamplerInterface()
    {
        $config = $this->createMock(ConfigInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $resource = $this->createMock(ResourceInfo::class);

        $samplerFactory = $this->createMock(SamplerFactory::class);
        $sampler = $this->createMock(SamplerInterface::class);
        $samplerFactory->method('make')->willReturn($sampler);

        $config->method('get')
            ->willReturnMap([
                ['open-telemetry.traces.sampler', 'always_on', 'always_on'],
                ['open-telemetry.traces.samplers.always_on.driver', null, 'sampler.driver'],
            ]);
        $container->method('get')
            ->with('sampler.driver')
            ->willReturn($samplerFactory);

        $factory = new TracerProviderFactory($config, $container, $resource);
        $result = $factory->getSampler();
        $this->assertInstanceOf(SamplerInterface::class, $result);
    }
}
