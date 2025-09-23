<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Trace\Sampler;

use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Factory\Trace\Sampler\AlwaysOnSamplerFactory;

/**
 * @internal
 */
class AlwaysOnSamplerFactoryTest extends TestCase
{
    public function testMakeReturnsAlwaysOnSampler()
    {
        $factory = new AlwaysOnSamplerFactory();
        $sampler = $factory->make();
        $this->assertInstanceOf(AlwaysOnSampler::class, $sampler);
        $this->assertInstanceOf(SamplerInterface::class, $sampler);
    }
}
