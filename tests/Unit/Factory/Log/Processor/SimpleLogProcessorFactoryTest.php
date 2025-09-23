<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Log\Processor;

use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\LogRecordProcessorInterface;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Factory\Log\Processor\SimpleLogProcessorFactory;

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
