<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Metric\Exporter;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Factory\Metric\Exporter\OtlpGrpcMetricExporterFactory;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class OtlpGrpcMetricExporterFactoryTest extends TestCase
{
    public function testMake(): void
    {
        $options = [
            'endpoint' => 'http://collector:4317',
            'headers' => ['Authorization' => 'Bearer token'],
            'compression' => 'gzip',
            'timeout' => 20,
            'retry_delay' => 200,
            'max_retries' => 5,
            'temporality' => Temporality::CUMULATIVE,
        ];
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.metrics.exporters.otlp_grpc.options', [])
            ->willReturn($options);

        $factory = new OtlpGrpcMetricExporterFactory($config);
        $exporter = $factory->make();

        $this->assertInstanceOf(MetricExporterInterface::class, $exporter);
    }
}
