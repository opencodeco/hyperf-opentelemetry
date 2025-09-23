<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SemConv\Attributes\ServiceAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\ServiceIncubatingAttributes;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

use function Hyperf\Support\env;

class OTelResourceFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): ResourceInfo
    {
        $config = $container->get(ConfigInterface::class);

        $resourceConfig = $config->get('open-telemetry.resource');

        if (! is_array($resourceConfig)) {
            $resourceConfig = [
                ServiceAttributes::SERVICE_NAME => $config->get('app_name', 'hyperf'),
                ServiceIncubatingAttributes::SERVICE_NAMESPACE => env('APP_NAMESPACE', 'hyperf-opentelemetry'),
                ServiceIncubatingAttributes::SERVICE_INSTANCE_ID => gethostname() ?: uniqid(),
            ];
        }

        return ResourceInfoFactory::defaultResource()->merge(
            ResourceInfo::create(Attributes::create($resourceConfig))
        );
    }
}
