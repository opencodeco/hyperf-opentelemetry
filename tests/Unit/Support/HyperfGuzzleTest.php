<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Guzzle\CoroutineHandler;
use Hyperf\Guzzle\PoolHandler;
use Hyperf\OpenTelemetry\Support\HyperfGuzzle;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionAttribute;
use ReflectionProperty;

/**
 * @internal
 */
class HyperfGuzzleTest extends TestCase
{
    private ?ContainerInterface $container = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (ApplicationContext::hasContainer()) {
            $this->container = ApplicationContext::getContainer();
        }
    }

    protected function tearDown(): void
    {
        m::close();

        if ($this->container !== null) {
            ApplicationContext::setContainer($this->container);
        }
        parent::tearDown();
    }

    public function testAvailableReturnsTrueWhenCoroutineHandlerExistsAndContainerAvailable()
    {
        $container = m::mock(ContainerInterface::class);
        ApplicationContext::setContainer($container);

        $hyperfGuzzle = new HyperfGuzzle();
        $this->assertTrue($hyperfGuzzle->available());
    }

    public function testCreateReturnsClientWithCoroutineHandlerWhenPoolConfigIsEmpty()
    {
        $container = m::mock(ContainerInterface::class);
        $config = m::mock(ConfigInterface::class);

        $config->shouldReceive('get')
            ->with('open-telemetry.otlp_http.pool', [])
            ->andReturn([]);

        $container->shouldReceive('get')
            ->with(ConfigInterface::class)
            ->andReturn($config);

        ApplicationContext::setContainer($container);

        $hyperfGuzzle = new HyperfGuzzle();
        $options = ['timeout' => 30];
        $client = $hyperfGuzzle->create($options);

        $this->assertInstanceOf(Client::class, $client);

        /** @var Client $client */
        $clientConfig = $client->getConfig();

        $this->assertIsArray($clientConfig);
        $this->assertInstanceOf(HandlerStack::class, $clientConfig['handler']);

        $property = new ReflectionProperty(HandlerStack::class, 'handler');

        $this->assertInstanceOf(CoroutineHandler::class, $property->getValue($clientConfig['handler']));
    }

    public function testCreateReturnsClientWithPoolHandlerWhenPoolConfigIsProvided()
    {
        $container = m::mock(ContainerInterface::class);
        $config = m::mock(ConfigInterface::class);
        $poolHandler = m::mock(PoolHandler::class);

        $poolConfig = [
            'min_connections' => 1,
            'max_connections' => 10,
        ];

        $config->shouldReceive('get')
            ->with('open-telemetry.otlp_http.pool', [])
            ->andReturn($poolConfig);

        $container->shouldReceive('get')
            ->with(ConfigInterface::class)
            ->andReturn($config);

        $container->shouldReceive('make')
            ->with(PoolHandler::class, ['option' => $poolConfig])
            ->andReturn($poolHandler);

        ApplicationContext::setContainer($container);

        $hyperfGuzzle = new HyperfGuzzle();
        $options = ['timeout' => 30];
        $client = $hyperfGuzzle->create($options);

        $this->assertInstanceOf(Client::class, $client);

        /** @var Client $client */
        $clientConfig = $client->getConfig();

        $this->assertIsArray($clientConfig);
        $this->assertInstanceOf(HandlerStack::class, $clientConfig['handler']);

        $property = new ReflectionProperty(HandlerStack::class, 'handler');

        $this->assertInstanceOf(PoolHandler::class, $property->getValue($clientConfig['handler']));
    }

    public function testCreateMergesOptionsWithHandler()
    {
        $container = m::mock(ContainerInterface::class);
        $config = m::mock(ConfigInterface::class);

        $config->shouldReceive('get')
            ->with('open-telemetry.otlp_http.pool', [])
            ->andReturn([]);

        $container->shouldReceive('get')
            ->with(ConfigInterface::class)
            ->andReturn($config);

        ApplicationContext::setContainer($container);

        $hyperfGuzzle = new HyperfGuzzle();
        $options = [
            'timeout' => 30,
            'base_uri' => 'http://localhost',
        ];

        /** @var Client $client */
        $client = $hyperfGuzzle->create($options);

        $this->assertInstanceOf(Client::class, $client);

        $config = $client->getConfig();
        $this->assertEquals(30, $config['timeout']);
        $this->assertEquals('http://localhost', $config['base_uri']);
        $this->assertInstanceOf(HandlerStack::class, $config['handler']);
    }

    public function testCreateWithEmptyOptions()
    {
        $container = m::mock(ContainerInterface::class);
        $config = m::mock(ConfigInterface::class);

        $config->shouldReceive('get')
            ->with('open-telemetry.otlp_http.pool', [])
            ->andReturn([]);

        $container->shouldReceive('get')
            ->with(ConfigInterface::class)
            ->andReturn($config);

        ApplicationContext::setContainer($container);

        $hyperfGuzzle = new HyperfGuzzle();

        /** @var Client $client */
        $client = $hyperfGuzzle->create(null);

        $this->assertInstanceOf(Client::class, $client);

        $clientConfig = $client->getConfig();

        $this->assertIsArray($clientConfig);
        $this->assertInstanceOf(HandlerStack::class, $clientConfig['handler']);

        $property = new ReflectionProperty(HandlerStack::class, 'handler');

        $this->assertInstanceOf(CoroutineHandler::class, $property->getValue($clientConfig['handler']));
    }
}
