<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry;

use Hyperf\Contract\ConfigInterface;

class Switcher
{
    public function __construct(
        protected readonly Instrumentation $instrumentation,
        protected readonly ConfigInterface $config,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->get('open-telemetry.instrumentation.enabled', true);
    }

    public function isTracingEnabled(?string $key = null): bool
    {
        if ($key === null) {
            return $this->instrumentation->tracer()->isEnabled()
                    && $this->isEnabled()
                    && $this->config->get('open-telemetry.traces.enabled', true);
        }

        return $this->isTracingEnabled()
                && $this->config->get("open-telemetry.instrumentation.features.{$key}.traces", true);
    }

    public function isMetricsEnabled(?string $key = null): bool
    {
        if ($key === null) {
            return $this->isEnabled()
                && $this->config->get('open-telemetry.metrics.enabled', true);
        }

        return $this->isMetricsEnabled()
            && $this->config->get("open-telemetry.instrumentation.features.{$key}.metrics", true);
    }
}
