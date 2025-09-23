<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Metric\Exporter;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Factory\Metric\Exporter\OtlpHttpMetricExporterFactory;

/**
 * @internal
 */
class OtlpHttpMetricExporterFactoryTest extends TestCase
{
    public function testMakeWithCustomOptions()
    {
        $options = [
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'content_type' => 'application/json',
            'headers' => ['Authorization' => 'Bearer token'],
            'compression' => 'none',
            'timeout' => 20,
            'retry_delay' => 200,
            'max_retries' => 5,
            'temporality' => Temporality::DELTA,
        ];
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.metrics.exporters.otlp_http.options', [])
            ->willReturn($options);

        $factory = new OtlpHttpMetricExporterFactory($config);
        $exporter = $factory->make();

        $this->assertInstanceOf(MetricExporterInterface::class, $exporter);
    }
}
