<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Hyperf\OpenTelemetry\Support\SwooleCoroutineDetector;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

/**
 * @internal
 */
class SwooleCoroutineDetectorTest extends TestCase
{
    public function testInactiveWithoutExtensionEvenInsideACoroutineId(): void
    {
        $detector = new ProbeCoroutineDetector(loaded: false, coroutineId: 3);

        $this->assertFalse($detector->extensionLoaded());
        $this->assertFalse($detector->isActive());
    }

    public function testInactiveWhenExtensionIsLoadedButNoCoroutineIsRunning(): void
    {
        $detector = new ProbeCoroutineDetector(loaded: true, coroutineId: -1);

        $this->assertTrue($detector->extensionLoaded());
        $this->assertFalse($detector->isActive());
    }

    public function testActiveWhenExtensionIsLoadedInsideACoroutine(): void
    {
        $detector = new ProbeCoroutineDetector(loaded: true, coroutineId: 2);

        $this->assertTrue($detector->isActive());
    }

    public function testLiveDetectorMatchesTheProcess(): void
    {
        $detector = new SwooleCoroutineDetector();

        if (! extension_loaded('swoole')) {
            $this->assertFalse($detector->extensionLoaded());
            $this->assertFalse($detector->isActive());

            return;
        }

        $this->assertTrue($detector->extensionLoaded());
        $this->assertSame(Coroutine::getCid() > 0, $detector->isActive());
    }
}

class ProbeCoroutineDetector extends SwooleCoroutineDetector
{
    public function __construct(
        private readonly bool $loaded,
        private readonly int $coroutineId,
    ) {
    }

    public function extensionLoaded(): bool
    {
        return $this->loaded;
    }

    protected function coroutineId(): int
    {
        return $this->coroutineId;
    }
}
