<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Metric\Exporter;

use OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Factory\Metric\Exporter\StdoutMetricExporterFactory;

/**
 * @internal
 */
class StdoutMetricExporterFactoryTest extends TestCase
{
    public function testMakeReturnsMetricExporterInterface()
    {
        $factory = new StdoutMetricExporterFactory();
        $exporter = $factory->make();
        $this->assertInstanceOf(MetricExporterInterface::class, $exporter);
    }
}
