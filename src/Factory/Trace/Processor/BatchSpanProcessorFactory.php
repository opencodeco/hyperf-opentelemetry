<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Trace\Processor;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;

class BatchSpanProcessorFactory implements TraceProcessorFactoryInterface
{
    public function __construct(
        protected readonly ConfigInterface $config,
    ) {
    }

    public function make(SpanExporterInterface $exporter): SpanProcessorInterface
    {
        $options = $this->config->get('open-telemetry.traces.processors.batch.options', []);

        return new BatchSpanProcessor(
            exporter: $exporter,
            clock: Clock::getDefault(),
            maxQueueSize: $options['max_queue_size'] ?? BatchSpanProcessor::DEFAULT_MAX_EXPORT_BATCH_SIZE,
            scheduledDelayMillis: $options['schedule_delay_ms'] ?? BatchSpanProcessor::DEFAULT_SCHEDULE_DELAY,
            exportTimeoutMillis: $options['export_timeout_ms'] ?? BatchSpanProcessor::DEFAULT_EXPORT_TIMEOUT,
            maxExportBatchSize: $options['max_export_batch_size'] ?? BatchSpanProcessor::DEFAULT_MAX_EXPORT_BATCH_SIZE,
            autoFlush: $options['auto_flush'] ?? true,
        );
    }
}
