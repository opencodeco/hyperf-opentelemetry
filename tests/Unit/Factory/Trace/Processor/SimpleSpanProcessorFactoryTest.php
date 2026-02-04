<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Trace\Processor;

use Hyperf\OpenTelemetry\Factory\Trace\Processor\SimpleSpanProcessorFactory;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class SimpleSpanProcessorFactoryTest extends TestCase
{
    public function testMakeReturnsSimpleSpanProcessor()
    {
        $exporter = $this->createMock(SpanExporterInterface::class);
        $factory = new SimpleSpanProcessorFactory();
        $processor = $factory->make($exporter);
        $this->assertInstanceOf(SimpleSpanProcessor::class, $processor);
        $this->assertInstanceOf(SpanProcessorInterface::class, $processor);
    }
}
