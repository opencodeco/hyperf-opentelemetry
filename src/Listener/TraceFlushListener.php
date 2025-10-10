<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

class TraceFlushListener extends AbstractFlushListener implements ListenerInterface
{
    public function __construct(
        ContainerInterface $container,
        ConfigInterface $config,
        StdoutLoggerInterface $logger,
        protected readonly TracerProviderInterface $tracerProvider,
    ) {
        parent::__construct($container, $config, $logger);
    }

    function flush(): void
    {
        $this->tracerProvider->forceFlush();
    }

    function exportInterval(): float
    {
        return (float) $this->config->get('open-telemetry.traces.export_interval', 5);
    }
}
