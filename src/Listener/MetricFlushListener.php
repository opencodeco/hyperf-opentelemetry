<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;

class MetricFlushListener extends AbstractFlushListener implements ListenerInterface
{
    public function __construct(
        ContainerInterface $container,
        ConfigInterface $config,
        StdoutLoggerInterface $logger,
        protected readonly MeterProviderInterface $meterProvider,
    ) {
        parent::__construct($container, $config, $logger);
    }

    function flush(): void
    {
        $this->meterProvider->forceFlush();
    }

    function exportInterval(): float
    {
        return (float) $this->config->get('open-telemetry.metrics.export_interval', 5);
    }
}
