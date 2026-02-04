<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Switcher;
use OpenTelemetry\API\Trace\TracerInterface;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class SwitcherTest extends TestCase
{
    public function testIsEnabledReturnsTrueByDefault()
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->willReturn(true);

        $instrumentation = $this->createMock(Instrumentation::class);

        $switcher = new Switcher($instrumentation, $config);

        $this->assertTrue($switcher->isEnabled());
    }

    public function testIsEnabledReturnsFalseIfConfigIsFalse()
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->willReturn(false);

        $instrumentation = $this->createMock(Instrumentation::class);

        $switcher = new Switcher($instrumentation, $config);

        $this->assertFalse($switcher->isEnabled());
    }

    public function testIsTracingEnabledReturnsTrue()
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->willReturn(true);

        $tracer = $this->createMock(TracerInterface::class);
        $tracer->method('isEnabled')->willReturn(true);

        $instrumentation = $this->createMock(Instrumentation::class);
        $instrumentation->method('tracer')->willReturn($tracer);

        $switcher = new Switcher($instrumentation, $config);

        $this->assertTrue($switcher->isTracingEnabled());
    }

    public function testIsTracingEnabledReturnsFalseIfTracerIsDisabled()
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->willReturn(true);

        $instrumentation = $this->createMock(Instrumentation::class);

        $switcher = new Switcher($instrumentation, $config);

        $this->assertFalse($switcher->isTracingEnabled());
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testIsTracingEnableForFeature(bool $expected): void
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->willReturn($expected);

        $tracer = $this->createMock(TracerInterface::class);
        $tracer->method('isEnabled')->willReturn(true);

        $instrumentation = $this->createMock(Instrumentation::class);
        $instrumentation->method('tracer')->willReturn($tracer);

        $switcher = new Switcher($instrumentation, $config);

        $this->assertEquals($expected, $switcher->isTracingEnabled('feature'));
    }

    public function testIsMetricsEnabledReturnsTrueWhenAllAreTrue()
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->willReturnMap([
            ['open-telemetry.instrumentation.enabled', true, true],
            ['open-telemetry.metrics.enabled', true, true],
            ['open-telemetry.instrumentation.features.feature1.metrics', true, true],
        ]);

        $instrumentation = $this->createMock(Instrumentation::class);

        $switcher = new Switcher($instrumentation, $config);

        $this->assertTrue($switcher->isMetricsEnabled());
        $this->assertTrue($switcher->isMetricsEnabled('feature1'));
    }

    public function testIsMetricsEnabledReturnsFalseIfConfigIsFalse()
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->willReturn(false);

        $instrumentation = $this->createMock(Instrumentation::class);

        $switcher = new Switcher($instrumentation, $config);

        $this->assertFalse($switcher->isMetricsEnabled());
    }
}
