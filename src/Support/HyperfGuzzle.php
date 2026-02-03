<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Support;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\CoroutineHandler;
use Hyperf\Guzzle\PoolHandler;
use OpenTelemetry\SDK\Common\Http\Psr\Client\Discovery\DiscoveryInterface;
use Psr\Http\Client\ClientInterface;

class HyperfGuzzle implements DiscoveryInterface
{
    public function available(): bool
    {
        return class_exists(CoroutineHandler::class) && ApplicationContext::hasContainer();
    }

    public function create(mixed $options): ClientInterface
    {
        $options = is_array($options) ? $options : [];

        return new Client(array_merge($options, [
            'handler' => $this->createHandler(),
        ]));
    }

    private function createHandler(): HandlerStack
    {
        /** @var ContainerInterface $container */
        $container = ApplicationContext::getContainer();

        $config = $container->get(ConfigInterface::class)->get('open-telemetry.otlp_http.pool', []);

        if (empty($config)) {
            return HandlerStack::create(new CoroutineHandler());
        }

        return HandlerStack::create($container->make(PoolHandler::class, ['option' => $config]));
    }
}
