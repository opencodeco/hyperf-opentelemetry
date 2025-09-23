<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Trace\Exporter;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Factory\Trace\Exporter\OtlpHttpTraceExporterFactory;

/**
 * @internal
 */
class OtlpHttpTraceExporterFactoryTest extends TestCase
{
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
}
