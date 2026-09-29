<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Log\Exporter;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Factory\Log\Exporter\OtlpHttpLogExporterFactory;
use Hyperf\OpenTelemetry\Support\OtlpHttpTransportBuilder;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Factory\Exporter\OtlpHttpClientAssertions;

/**
 * @internal
 */
class OtlpHttpLogExporterFactoryTest extends TestCase
{
    use OtlpHttpClientAssertions;

    public function testMakeWithCustomOptions()
    {
        $options = [
            'endpoint' => 'http://localhost:4318/v1/logs',
            'content_type' => 'application/json',
            'headers' => ['Authorization' => 'Bearer token'],
            'compression' => 'none',
        ];
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.logs.exporters.otlp_http.options', [])
            ->willReturn($options);

        $factory = new OtlpHttpLogExporterFactory($config);
        $exporter = $factory->make();

        $this->assertInstanceOf(LogRecordExporterInterface::class, $exporter);
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
    private function factory(array $options, ?OtlpHttpTransportBuilder $builder = null): OtlpHttpLogExporterFactory
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.logs.exporters.otlp_http.options', [])
            ->willReturn($options);

        return new OtlpHttpLogExporterFactory($config, $builder ?? new OtlpHttpTransportBuilder());
    }

    /**
     * @return array<string, mixed>
     */
    private function exporterOptions(): array
    {
        return [
            'endpoint' => 'http://localhost:4318/v1/logs',
            'content_type' => 'application/json',
            'headers' => ['Authorization' => 'Bearer token'],
            'compression' => 'none',
            'timeout' => 20,
            'retry_delay' => 200,
            'max_retries' => 5,
        ];
    }
}
