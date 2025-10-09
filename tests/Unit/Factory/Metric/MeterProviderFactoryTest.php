<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Metric;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricReaderInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use PHPUnit\Framework\TestCase;
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
        $metricReader = $this->createMock(MetricReaderInterface::class);

        $factory = new MeterProviderFactory($config, $container, $resource, $metricReader);
        $meterProvider = $factory($container);
        $this->assertInstanceOf(MeterProviderInterface::class, $meterProvider);
    }
}
