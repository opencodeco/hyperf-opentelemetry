<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Metric\Exporter;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Factory\Metric\Exporter\OtlpHttpMetricExporterFactory;
use Hyperf\OpenTelemetry\Support\OtlpHttpTransportBuilder;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Factory\Exporter\OtlpHttpClientAssertions;

/**
 * @internal
 */
class OtlpHttpMetricExporterFactoryTest extends TestCase
{
    use OtlpHttpClientAssertions;

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

    public function testMakeSelectsCoroutineClientWhenSwooleCoroutineIsActive(): void
    {
        $options = $this->exporterOptions();
        $this->assertSelectsCoroutineClient(
            fn (OtlpHttpTransportBuilder $builder) => $this->factory($options, $builder)->make(),
            $options['endpoint'],
            $options['content_type'],
        );
    }

    public function testMakeKeepsDiscoveredClientWhenSwooleIsAbsent(): void
    {
        $options = $this->exporterOptions();
        $this->assertKeepsDiscoveredClient(
            fn (OtlpHttpTransportBuilder $builder) => $this->factory($options, $builder)->make(),
            $options,
            $options['endpoint'],
            $options['content_type'],
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function factory(array $options, ?OtlpHttpTransportBuilder $builder = null): OtlpHttpMetricExporterFactory
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.metrics.exporters.otlp_http.options', [])
            ->willReturn($options);

        return new OtlpHttpMetricExporterFactory($config, $builder ?? new OtlpHttpTransportBuilder());
    }

    /**
     * @return array<string, mixed>
     */
    private function exporterOptions(): array
    {
        return [
            'endpoint' => 'http://localhost:4318/v1/metrics',
            'content_type' => 'application/json',
            'headers' => ['Authorization' => 'Bearer token'],
            'compression' => 'none',
            'timeout' => 20,
            'retry_delay' => 200,
            'max_retries' => 5,
            'temporality' => Temporality::DELTA,
        ];
    }
}
