<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Logs\NoopLoggerProvider;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\NoopMeterProvider;
use OpenTelemetry\SDK\Trace\NoopTracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Factory\Log\LoggerProviderFactory;
use Hyperf\OpenTelemetry\Factory\Metric\MeterProviderFactory;
use Hyperf\OpenTelemetry\Factory\SDKBuilder;
use Hyperf\OpenTelemetry\Factory\Trace\TracerProviderFactory;

/**
 * @internal
 */
class SDKBuilderTest extends TestCase
{
    private ConfigInterface $config;

    private LoggerProviderFactory $loggerProviderFactory;

    private TracerProviderFactory $tracerProviderFactory;

    private MeterProviderFactory $meterProviderFactory;

    private TracerProviderInterface $tracerProvider;

    private MeterProviderInterface $meterProvider;

    private LoggerProviderInterface $loggerProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(ConfigInterface::class);
        $this->tracerProvider = $this->createMock(TracerProviderInterface::class);
        $this->meterProvider = $this->createMock(MeterProviderInterface::class);
        $this->loggerProvider = $this->createMock(LoggerProviderInterface::class);

        $this->config->method('get')->willReturnMap([
            ['open-telemetry.traces.enabled', false, true],
            ['open-telemetry.metrics.enabled', false, true],
            ['open-telemetry.logs.enabled', false, true],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('OTEL_SDK_DISABLED');
    }

    public function testShouldRegisterNoopWhenOtelIsDisabled(): void
    {
        putenv('OTEL_SDK_DISABLED=true');

        $builder = new SDKBuilder(
            $this->loggerProvider,
            $this->tracerProvider,
            $this->meterProvider
        );

        $builder->build();

        $this->assertInstanceOf(NoopTracerProvider::class, Globals::tracerProvider());
        $this->assertInstanceOf(NoopMeterProvider::class, Globals::meterProvider());
        $this->assertInstanceOf(NoopLoggerProvider::class, Globals::loggerProvider());
    }

    public function testBuild(): void
    {
        $builder = new SDKBuilder(
            $this->loggerProvider,
            $this->tracerProvider,
            $this->meterProvider
        );

        $builder->build();

        $this->assertEquals($this->tracerProvider, Globals::tracerProvider());
        $this->assertEquals($this->meterProvider, Globals::meterProvider());
        $this->assertEquals($this->loggerProvider, Globals::loggerProvider());
        $this->assertInstanceOf(TraceContextPropagator::class, Globals::propagator());
    }
}
