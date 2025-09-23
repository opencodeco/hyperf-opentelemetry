<?php

declare(strict_types=1);

namespace Tests\Unit\Propagator;

use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Propagator\HeadersPropagator;
use Psr\Http\Message\RequestInterface;

/**
 * @internal
 */
class HeadersPropagatorTest extends TestCase
{
    public function testInstanceReturnsSingleton()
    {
        $first = HeadersPropagator::instance();
        $second = HeadersPropagator::instance();

        $this->assertInstanceOf(HeadersPropagator::class, $first);
        $this->assertSame($first, $second);
    }

    public function testSetAddsHeaderToRequest()
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('withAddedHeader')
            ->with('X-Test', 'value')
            ->willReturnSelf();

        $carrier = $request;
        $propagator = HeadersPropagator::instance();
        $propagator->set($carrier, 'X-Test', 'value');

        $this->assertSame($request, $carrier);
    }
}
