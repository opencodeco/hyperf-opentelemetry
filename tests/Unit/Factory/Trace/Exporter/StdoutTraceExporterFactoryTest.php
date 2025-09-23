<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Trace\Exporter;

use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Factory\Trace\Exporter\StdoutTraceExporterFactory;

/**
 * @internal
 */
class StdoutTraceExporterFactoryTest extends TestCase
{
    public function testMakeReturnsSpanExporterInterface()
    {
        $factory = new StdoutTraceExporterFactory();
        $exporter = $factory->make();
        $this->assertInstanceOf(SpanExporterInterface::class, $exporter);
    }
}
