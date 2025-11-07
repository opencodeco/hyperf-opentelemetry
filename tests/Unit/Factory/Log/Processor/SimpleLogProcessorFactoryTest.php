<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Log\Processor;

use Hyperf\OpenTelemetry\Factory\Log\Processor\SimpleLogProcessorFactory;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\LogRecordProcessorInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class SimpleLogProcessorFactoryTest extends TestCase
{
    public function testMakeReturnsLogRecordProcessorInterface()
    {
        $exporter = $this->createMock(LogRecordExporterInterface::class);
        $factory = new SimpleLogProcessorFactory();
        $processor = $factory->make($exporter);
        $this->assertInstanceOf(LogRecordProcessorInterface::class, $processor);
    }
}
