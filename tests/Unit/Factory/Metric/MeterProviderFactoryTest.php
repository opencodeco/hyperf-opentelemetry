<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Metric;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\OpenTelemetry\Factory\Metric\MeterProviderFactory;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use PHPUnit\Framework\TestCase;

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

        $factory = new MeterProviderFactory($config, $container, $resource);
        $meterProvider = $factory($container);
        $this->assertInstanceOf(MeterProviderInterface::class, $meterProvider);
    }
}
