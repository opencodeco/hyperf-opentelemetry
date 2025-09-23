<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Trace\Sampler;

use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SamplerInterface;

class AlwaysOnSamplerFactory implements SamplerFactory
{
    public function make(): SamplerInterface
    {
        return new AlwaysOnSampler();
    }
}
