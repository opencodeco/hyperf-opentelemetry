<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Log\Exporter;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Factory\Log\Exporter\OtlpGrpcLogExporterFactory;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class OtlpGrpcLogExporterFactoryTest extends TestCase
{
    public function testMakeWithDefaultOptions(): void
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.logs.exporters.otlp_grpc.options', [])
            ->willReturn([]);

        $factory = new OtlpGrpcLogExporterFactory($config);
        $exporter = $factory->make();

        $this->assertInstanceOf(LogRecordExporterInterface::class, $exporter);
    }

    public function testMakeWithCustomOptions(): void
    {
        $options = [
            'endpoint' => 'http://collector:4317',
            'headers' => ['Authorization' => 'Bearer token'],
            'compression' => 'gzip',
            'timeout' => 20,
            'retry_delay' => 200,
            'max_retries' => 5,
        ];
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.logs.exporters.otlp_grpc.options', [])
            ->willReturn($options);

        $factory = new OtlpGrpcLogExporterFactory($config);
        $exporter = $factory->make();

        $this->assertInstanceOf(LogRecordExporterInterface::class, $exporter);
    }
}
