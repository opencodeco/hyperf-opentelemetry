<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory;

use Hyperf\Contract\ContainerInterface;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorage;
use OpenTelemetry\Contrib\Context\Swoole\SwooleContextStorage;
use OpenTelemetry\SemConv\Version;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class CachedInstrumentationFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): CachedInstrumentation
    {
        if (! Context::storage() instanceof SwooleContextStorage) {
            Context::setStorage(new SwooleContextStorage(new ContextStorage()));
        }

        $builder = $container->get(SDKBuilder::class);
        $builder->build();

        return new CachedInstrumentation(
            name: 'hyperf/opentelemetry',
            schemaUrl: Version::VERSION_1_27_0->url(),
            attributes: [
                'instrumentation.name' => 'hyperf/open-telemetry',
            ],
        );
    }
}
