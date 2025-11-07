<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\OpenTelemetry\Factory\OTelResourceFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\Attributes\ServiceAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\ServiceIncubatingAttributes;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class OTelResourceFactoryTest extends TestCase
{
    public function testInvokeReturnsResourceInfoWithDefaultValues()
    {
        $container = $this->createMock(ContainerInterface::class);
        $config = $this->createMock(ConfigInterface::class);
        $container->method('get')
            ->with(ConfigInterface::class)
            ->willReturn($config);
        $config->method('get')
            ->willReturnMap([
                ['open-telemetry.resource', [
                    ServiceAttributes::SERVICE_NAME => 'hyperf',
                    ServiceIncubatingAttributes::SERVICE_NAMESPACE => 'hyperf-opentelemetry',
                    ServiceIncubatingAttributes::SERVICE_INSTANCE_ID => 'instance-id',
                ], [
                    ServiceAttributes::SERVICE_NAME => 'hyperf',
                    ServiceIncubatingAttributes::SERVICE_NAMESPACE => 'hyperf-opentelemetry',
                    ServiceIncubatingAttributes::SERVICE_INSTANCE_ID => 'instance-id',
                ]],
                ['app_name', 'hyperf', 'hyperf'],
            ]);

        $factory = new OTelResourceFactory();
        $resource = $factory->__invoke($container);
        $this->assertInstanceOf(ResourceInfo::class, $resource);
        $attributes = $resource->getAttributes()->toArray();
        $this->assertEquals('hyperf', $attributes[ServiceAttributes::SERVICE_NAME]);
        $this->assertEquals('hyperf-opentelemetry', $attributes[ServiceIncubatingAttributes::SERVICE_NAMESPACE]);
        $this->assertNotEmpty($attributes[ServiceIncubatingAttributes::SERVICE_INSTANCE_ID]);
    }

    public function testInvokeReturnsResourceInfoWithCustomValues()
    {
        $container = $this->createMock(ContainerInterface::class);
        $config = $this->createMock(ConfigInterface::class);
        $container->method('get')
            ->with(ConfigInterface::class)
            ->willReturn($config);
        $customAttributes = [
            ServiceAttributes::SERVICE_NAME => 'custom-service',
            ServiceIncubatingAttributes::SERVICE_NAMESPACE => 'custom-namespace',
            ServiceIncubatingAttributes::SERVICE_INSTANCE_ID => 'custom-instance',
        ];
        $config->method('get')
            ->willReturnCallback(function ($key) use ($customAttributes) {
                if ($key === 'open-telemetry.resource') {
                    return $customAttributes;
                }
                if ($key === 'app_name') {
                    return 'custom-service';
                }
                return null;
            });

        $factory = new OTelResourceFactory();
        $resource = $factory->__invoke($container);
        $this->assertInstanceOf(ResourceInfo::class, $resource);
        $attributes = $resource->getAttributes()->toArray();
        $this->assertEquals('custom-service', $attributes[ServiceAttributes::SERVICE_NAME]);
        $this->assertEquals('custom-namespace', $attributes[ServiceIncubatingAttributes::SERVICE_NAMESPACE]);
        $this->assertEquals('custom-instance', $attributes[ServiceIncubatingAttributes::SERVICE_INSTANCE_ID]);
    }

    public function testInvokeReturnsResourceInfoWithNullResourceConfig()
    {
        $container = $this->createMock(ContainerInterface::class);
        $config = $this->createMock(ConfigInterface::class);
        $container->method('get')
            ->with(ConfigInterface::class)
            ->willReturn($config);
        $config->method('get')
            ->willReturnMap([
                ['open-telemetry.resource', null, null],
                ['app_name', 'hyperf', 'hyperf'],
            ]);

        $factory = new OTelResourceFactory();
        $resource = $factory->__invoke($container);
        $this->assertInstanceOf(ResourceInfo::class, $resource);
        $attributes = $resource->getAttributes()->toArray();
        $this->assertEquals('hyperf', $attributes[ServiceAttributes::SERVICE_NAME]);
        $this->assertEquals('hyperf-opentelemetry', $attributes[ServiceIncubatingAttributes::SERVICE_NAMESPACE]);
        $this->assertNotEmpty($attributes[ServiceIncubatingAttributes::SERVICE_INSTANCE_ID]);
    }

    public function testInvokeReturnsResourceInfoWithNonArrayResourceConfig()
    {
        $container = $this->createMock(ContainerInterface::class);
        $config = $this->createMock(ConfigInterface::class);
        $container->method('get')
            ->with(ConfigInterface::class)
            ->willReturn($config);
        $config->method('get')
            ->willReturnMap([
                ['open-telemetry.resource', 'not-an-array', 'not-an-array'],
                ['app_name', 'hyperf', 'hyperf'],
            ]);

        $factory = new OTelResourceFactory();
        $resource = $factory->__invoke($container);
        $this->assertInstanceOf(ResourceInfo::class, $resource);
        $attributes = $resource->getAttributes()->toArray();
        $this->assertEquals('hyperf', $attributes[ServiceAttributes::SERVICE_NAME]);
        $this->assertEquals('hyperf-opentelemetry', $attributes[ServiceIncubatingAttributes::SERVICE_NAMESPACE]);
        $this->assertNotEmpty($attributes[ServiceIncubatingAttributes::SERVICE_INSTANCE_ID]);
    }
}
