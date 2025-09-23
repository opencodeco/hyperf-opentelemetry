<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Log\Exporter;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Factory\Log\Exporter\OtlpHttpLogExporterFactory;

/**
 * @internal
 */
class OtlpHttpLogExporterFactoryTest extends TestCase
{
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
}
