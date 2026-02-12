<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Trace\Exporter;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Factory\Trace\Exporter\OtlpGrpcTraceExporterFactory;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class OtlpGrpcTraceExporterFactoryTest extends TestCase
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
        ];
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')
            ->with('open-telemetry.traces.exporters.otlp_grpc.options', [])
            ->willReturn($options);

        $factory = new OtlpGrpcTraceExporterFactory($config);
        $exporter = $factory->make();

        $this->assertInstanceOf(SpanExporterInterface::class, $exporter);
    }
}
