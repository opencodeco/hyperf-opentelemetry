<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Trace\Sampler;

use OpenTelemetry\SDK\Trace\SamplerInterface;

interface SamplerFactory
{
    public function make(): SamplerInterface;
}
