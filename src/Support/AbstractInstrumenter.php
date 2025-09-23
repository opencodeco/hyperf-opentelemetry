<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Support;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Switcher;

abstract class AbstractInstrumenter
{
    protected bool $isTracingEnabled = false;

    protected bool $isMetricsEnabled = false;

    public function __construct(
        protected readonly ConfigInterface $config,
        protected readonly Instrumentation $instrumentation,
        protected readonly Switcher $switcher,
    ) {
        $this->refreshTelemetryFlags($this->featureName());
    }

    abstract protected function featureName(): string;

    protected function refreshTelemetryFlags(string $feature): void
    {
        $this->isTracingEnabled = $this->switcher->isTracingEnabled($feature);
        $this->isMetricsEnabled = $this->switcher->isMetricsEnabled($feature);
    }

    protected function isTelemetryEnabled(): bool
    {
        return $this->isTracingEnabled || $this->isMetricsEnabled;
    }
}
