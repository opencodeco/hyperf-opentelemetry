<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Log\Processor;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\LogRecordProcessorInterface;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor as BatchProcessor;

class BatchLogProcessorFactory implements LogProcessorFactoryInterface
{
    public function __construct(
        protected readonly ConfigInterface $config,
    ) {
    }

    public function make(LogRecordExporterInterface $exporter): LogRecordProcessorInterface
    {
        $options = $this->config->get('open-telemetry.logs.processors.batch.options', []);

        return new BatchProcessor(
            exporter: $exporter,
            clock: Clock::getDefault(),
            maxQueueSize: $options['max_queue_size'] ?? BatchProcessor::DEFAULT_MAX_EXPORT_BATCH_SIZE,
            scheduledDelayMillis: $options['schedule_delay_ms'] ?? BatchProcessor::DEFAULT_SCHEDULE_DELAY,
            exportTimeoutMillis: $options['export_timeout_ms'] ?? BatchProcessor::DEFAULT_EXPORT_TIMEOUT,
            maxExportBatchSize: $options['max_export_batch_size'] ?? BatchProcessor::DEFAULT_MAX_EXPORT_BATCH_SIZE,
            autoFlush: $options['auto_flush'] ?? true,
        );
    }
}
