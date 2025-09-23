<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Propagator;

use OpenTelemetry\Context\Propagation\PropagationSetterInterface;
use Psr\Http\Message\RequestInterface;

use function assert;

/**
 * @internal
 */
class HeadersPropagator implements PropagationSetterInterface
{
    public static function instance(): self
    {
        static $instance;

        return $instance ??= new self();
    }

    public function set(&$carrier, string $key, string $value): void
    {
        assert($carrier instanceof RequestInterface);

        $carrier = $carrier->withAddedHeader($key, $value);
    }
}
