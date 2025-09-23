<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Log\Exporter;

use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Factory\Log\Exporter\StdoutLogExporterFactory;

/**
 * @internal
 */
class StdoutLogExporterFactoryTest extends TestCase
{
    public function testMakeReturnsLogRecordExporterInterface()
    {
        $factory = new StdoutLogExporterFactory();
        $exporter = $factory->make();
        $this->assertInstanceOf(LogRecordExporterInterface::class, $exporter);
    }
}
