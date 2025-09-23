<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Aspect;

use Hyperf\Di\Aop\AroundInterface;
use Hyperf\OpenTelemetry\Support\AbstractInstrumenter;

abstract class AbstractAspect extends AbstractInstrumenter implements AroundInterface
{
    /**
     * The classes that you want to weave.
     */
    public array $classes = [];

    /**
     * The annotations that you want to weave.
     */
    public array $annotations = [];

    public ?int $priority = null;
}
