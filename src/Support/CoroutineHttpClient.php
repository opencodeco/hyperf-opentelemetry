<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Support;

use OpenTelemetry\SDK\Common\Http\Psr\Client\Discovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Picks a PSR-18 client when the request is sent.
 *
 * Exporters are built once per worker. The flush that follows can move into a
 * coroutine later, so the choice cannot be frozen at construction time.
 */
class CoroutineHttpClient implements ClientInterface
{
    private ?ClientInterface $coroutineClient = null;

    private ?ClientInterface $fallbackClient = null;

    public function __construct(
        private readonly SwooleCoroutineDetector $coroutines,
        private readonly float $timeout,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client()->sendRequest($request);
    }

    public function client(): ClientInterface
    {
        if ($this->coroutines->isActive()) {
            $hyperfGuzzle = new HyperfGuzzle();
            if ($hyperfGuzzle->available()) {
                return $this->coroutineClient ??= $hyperfGuzzle->create([
                    'timeout' => $this->timeout,
                ]);
            }
        }

        return $this->fallbackClient ??= Discovery::find([
            'timeout' => $this->timeout,
        ]);
    }
}
