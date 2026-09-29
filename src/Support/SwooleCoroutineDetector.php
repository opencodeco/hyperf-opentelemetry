<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Support;

use Swoole\Coroutine;

class SwooleCoroutineDetector
{
    public function extensionLoaded(): bool
    {
        return extension_loaded('swoole');
    }

    public function isActive(): bool
    {
        return $this->extensionLoaded() && $this->coroutineId() > 0;
    }

    protected function coroutineId(): int
    {
        if (! class_exists(Coroutine::class)) {
            return -1;
        }

        return Coroutine::getCid();
    }
}
