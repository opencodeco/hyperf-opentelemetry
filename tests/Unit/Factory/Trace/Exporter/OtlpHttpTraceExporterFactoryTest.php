<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Trace\Exporter;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Factory\Trace\Exporter\OtlpHttpTraceExporterFactory;
use Hyperf\OpenTelemetry\Support\OtlpHttpTransportBuilder;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Factory\Exporter\OtlpHttpClientAssertions;

/**
 * @internal
 */
class OtlpHttpTraceExporterFactoryTest extends TestCase
{
    use OtlpHttpClientAssertions;

    public function testMakeWithCustomOptions()
    {
        $options = [
            'endpoint' => 'http://localhost:4318/v1/traces',
            'content_type' => 'application/json',
            'headers' => ['Authorization' => 'Bearer token'],
            'compression' => 'none',
            'timeout' => 20,
            'retry_delay' => 200,
            'max_retries' => 5,
        ];
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.traces.exporters.otlp_http.options', [])
            ->willReturn($options);

        $factory = new OtlpHttpTraceExporterFactory($config);
        $exporter = $factory->make();

        $this->assertInstanceOf(SpanExporterInterface::class, $exporter);
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

    public function testMakeFallsBackOutsideAnActiveCoroutineEvenWhenSwooleIsLoaded(): void
    {
        $options = $this->exporterOptions();
        $this->assertFallsBackOutsideAnActiveCoroutine(
            fn (OtlpHttpTransportBuilder $builder) => $this->factory($options, $builder)->make(),
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
    private function factory(array $options, ?OtlpHttpTransportBuilder $builder = null): OtlpHttpTraceExporterFactory
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.traces.exporters.otlp_http.options', [])
            ->willReturn($options);

        return new OtlpHttpTraceExporterFactory($config, $builder ?? new OtlpHttpTransportBuilder());
    }

    /**
     * @return array<string, mixed>
     */
    private function exporterOptions(): array
    {
        return [
            'endpoint' => 'http://localhost:4318/v1/traces',
            'content_type' => 'application/json',
            'headers' => ['Authorization' => 'Bearer token'],
            'compression' => 'none',
            'timeout' => 20,
            'retry_delay' => 200,
            'max_retries' => 5,
        ];
    }
}
