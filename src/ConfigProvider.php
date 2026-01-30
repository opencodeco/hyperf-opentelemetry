<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry;

use Hyperf\OpenTelemetry\Factory\Log\LoggerProviderFactory;
use Hyperf\OpenTelemetry\Factory\Metric\MeterProviderFactory;
use Hyperf\OpenTelemetry\Factory\Trace\TracerProviderFactory;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use Hyperf\OpenTelemetry\Factory\CachedInstrumentationFactory;
use Hyperf\OpenTelemetry\Factory\OTelResourceFactory;
use Hyperf\OpenTelemetry\Support\HyperfGuzzle;
use OpenTelemetry\SDK\Common\Http\Psr\Client\Discovery;
use OpenTelemetry\SDK\Common\Http\Psr\Client\Discovery\Guzzle;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        defined('BASE_PATH') || define('BASE_PATH', '');

        Discovery::setDiscoverers([
            HyperfGuzzle::class,
            Guzzle::class,
        ]);

        return [
            'dependencies' => [
                CachedInstrumentation::class => CachedInstrumentationFactory::class,
                ResourceInfo::class => OTelResourceFactory::class,
                TracerProviderInterface::class => TracerProviderFactory::class,
                MeterProviderInterface::class => MeterProviderFactory::class,
                LoggerProviderInterface::class => LoggerProviderFactory::class,
            ],
            'listeners' => [
                Listener\MetricFlushListener::class,
                Listener\TraceFlushListener::class,
                Listener\OtelShutdownListener::class,
                Listener\DbQueryExecutedListener::class,
            ],
            'aspects' => [
                Aspect\RedisAspect::class,
                Aspect\GuzzleClientAspect::class,
                Aspect\Aws\DynamoDbClientAspect::class,
                Aspect\Aws\SnsClientAspect::class,
                Aspect\Aws\SqsClientAspect::class,
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for OpenTelemetry.',
                    'source' => __DIR__ . '/../publish/open-telemetry.php',
                    'destination' => BASE_PATH . '/config/autoload/open-telemetry.php',
                ],
            ],
        ];
    }
}
