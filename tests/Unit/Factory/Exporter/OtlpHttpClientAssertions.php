<?php

declare(strict_types=1);

namespace Tests\Unit\Factory\Exporter;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\CoroutineHandler;
use Hyperf\OpenTelemetry\Support\CoroutineHttpClient;
use Hyperf\OpenTelemetry\Support\HyperfGuzzle;
use Hyperf\OpenTelemetry\Support\OtlpHttpTransportBuilder;
use Hyperf\OpenTelemetry\Support\SwooleCoroutineDetector;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Http\Psr\Client\Discovery;
use OpenTelemetry\SDK\Common\Http\Psr\Client\Discovery\Guzzle;
use Psr\Container\ContainerInterface;
use ReflectionProperty;

trait OtlpHttpClientAssertions
{
    /**
     * @param callable(OtlpHttpTransportBuilder): object $make
     */
    protected function assertSelectsCoroutineClient(callable $make, string $endpoint, string $contentType): void
    {
        $this->withContainer($this->poolContainer([]), function () use ($make): void {
            $handler = $this->resolvedHandler($make($this->builder(extensionLoaded: true, coroutineActive: true)));
            $this->assertInstanceOf(CoroutineHandler::class, $handler);
            $this->assertSame(CoroutineHandler::class, $handler::class);
        });
    }

    /**
     * @param callable(OtlpHttpTransportBuilder): object $make
     * @param array<string, mixed> $options
     */
    protected function assertKeepsDiscoveredClient(
        callable $make,
        array $options,
        string $endpoint,
        string $contentType,
    ): void {
        $this->withDefaultDiscovery(function () use ($make, $options, $endpoint, $contentType): void {
            $this->withContainer(null, function () use ($make, $options, $endpoint, $contentType): void {
                $exporter = $make($this->builder(extensionLoaded: false, coroutineActive: false));
                $baseline = (new OtlpHttpTransportFactory())->create(
                    endpoint: $endpoint,
                    contentType: $contentType,
                    headers: $options['headers'] ?? [],
                    compression: $options['compression'] ?? null,
                    timeout: (float) ($options['timeout'] ?? 10),
                    retryDelay: (int) ($options['retry_delay'] ?? 100),
                    maxRetries: (int) ($options['max_retries'] ?? 3),
                );

                $client = $this->transportClient($exporter);
                $this->assertNotInstanceOf(CoroutineHttpClient::class, $client);
                $this->assertSame($this->handler($baseline)::class, $this->handler($exporter)::class);
                $this->assertNotInstanceOf(CoroutineHandler::class, $this->handler($exporter));
            });
        });
    }

    /**
     * @param callable(OtlpHttpTransportBuilder): object $make
     */
    protected function assertFallsBackOutsideAnActiveCoroutine(callable $make): void
    {
        $this->withDiscoverers([HyperfGuzzle::class, Guzzle::class], function () use ($make): void {
            $this->withContainer($this->poolContainer([]), function () use ($make): void {
                $exporter = $make($this->builder(extensionLoaded: true, coroutineActive: false));
                $client = $this->transportClient($exporter);
                $this->assertInstanceOf(CoroutineHttpClient::class, $client);
                $handler = $this->guzzleHandler($client->client());
                $this->assertNotInstanceOf(CoroutineHandler::class, $handler);
                $this->assertSame($this->blockingHandlerClass(), $handler::class);
            });
        });
    }

    protected function withDiscoverers(array $discoverers, callable $callback): mixed
    {
        Discovery::setDiscoverers($discoverers);

        try {
            return $callback();
        } finally {
            Discovery::setDiscoverers([
                HyperfGuzzle::class,
                Guzzle::class,
            ]);
        }
    }

    protected function withDefaultDiscovery(callable $callback): mixed
    {
        Discovery::reset();

        try {
            return $callback();
        } finally {
            Discovery::setDiscoverers([
                HyperfGuzzle::class,
                Guzzle::class,
            ]);
        }
    }

    protected function builder(bool $extensionLoaded, bool $coroutineActive): OtlpHttpTransportBuilder
    {
        $detector = $this->createMock(SwooleCoroutineDetector::class);
        $detector->method('extensionLoaded')->willReturn($extensionLoaded);
        $detector->method('isActive')->willReturn($coroutineActive);

        return new OtlpHttpTransportBuilder($detector);
    }

    protected function poolContainer(array $pool): ContainerInterface
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->willReturnCallback(
            static function (string $key, mixed $default = null) use ($pool): mixed {
                if ($key === 'open-telemetry.otlp_http.pool') {
                    return $pool;
                }

                return $default;
            }
        );

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(ConfigInterface::class)->willReturn($config);

        return $container;
    }

    protected function withContainer(?ContainerInterface $container, callable $callback): mixed
    {
        $property = new ReflectionProperty(ApplicationContext::class, 'container');
        $previous = $property->getValue();
        $property->setValue(null, $container);

        try {
            return $callback();
        } finally {
            $property->setValue(null, $previous);
        }
    }

    protected function resolvedHandler(object $exporter): object
    {
        $client = $this->transportClient($exporter);
        $this->assertInstanceOf(CoroutineHttpClient::class, $client);

        return $this->guzzleHandler($client->client());
    }

    protected function handler(object $transportOrExporter): object
    {
        return $this->guzzleHandler($this->transportClient($transportOrExporter));
    }

    private function blockingHandlerClass(): string
    {
        return $this->guzzleHandler((new Guzzle())->create(['timeout' => 10]))::class;
    }

    private function transportClient(object $transportOrExporter): object
    {
        $transport = $transportOrExporter instanceof TransportInterface
            ? $transportOrExporter
            : (new ReflectionProperty($transportOrExporter, 'transport'))->getValue($transportOrExporter);
        $client = (new ReflectionProperty($transport, 'client'))->getValue($transport);
        $this->assertIsObject($client);

        return $client;
    }

    private function guzzleHandler(object $client): object
    {
        $this->assertInstanceOf(Client::class, $client);
        /** @var Client $client */
        $stack = $client->getConfig('handler');
        $this->assertInstanceOf(HandlerStack::class, $stack);
        $handler = (new ReflectionProperty(HandlerStack::class, 'handler'))->getValue($stack);
        $this->assertIsObject($handler);

        return $handler;
    }
}
