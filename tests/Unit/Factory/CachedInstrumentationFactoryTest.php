<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use Hyperf\Contract\ContainerInterface;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorage;
use OpenTelemetry\Contrib\Context\Swoole\SwooleContextStorage;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Factory\CachedInstrumentationFactory;
use Hyperf\OpenTelemetry\Factory\SDKBuilder;

/**
 * @internal
 */
class CachedInstrumentationFactoryTest extends TestCase
{
    public function testInvokeReturnsCachedInstrumentation()
    {
        $container = $this->createMock(ContainerInterface::class);
        $sdkBuilder = $this->createMock(SDKBuilder::class);
        $sdkBuilder->expects($this->once())->method('build');
        $container->method('get')
            ->with(SDKBuilder::class)
            ->willReturn($sdkBuilder);

        // Garante que o Context::storage() não é SwooleContextStorage
        Context::setStorage(new ContextStorage());

        $factory = new CachedInstrumentationFactory();
        $result = $factory->__invoke($container);
        $this->assertInstanceOf(CachedInstrumentation::class, $result);
    }

    public function testInvokeDoesNotOverrideSwooleContextStorage()
    {
        $container = $this->createMock(ContainerInterface::class);
        $sdkBuilder = $this->createMock(SDKBuilder::class);
        $sdkBuilder->expects($this->once())->method('build');
        $container->method('get')
            ->with(SDKBuilder::class)
            ->willReturn($sdkBuilder);

        // Garante que o Context::storage() já é SwooleContextStorage
        Context::setStorage(new SwooleContextStorage(new ContextStorage()));

        $factory = new CachedInstrumentationFactory();
        $result = $factory->__invoke($container);
        $this->assertInstanceOf(CachedInstrumentation::class, $result);
    }
}
