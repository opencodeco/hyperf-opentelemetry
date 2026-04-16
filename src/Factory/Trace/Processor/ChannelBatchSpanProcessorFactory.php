<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Trace\Processor;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\SpanProcessor\ChannelBatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;

class ChannelBatchSpanProcessorFactory implements TraceProcessorFactoryInterface
{
    public function __construct(
        protected readonly ConfigInterface $config,
    ) {
    }

    public function make(SpanExporterInterface $exporter): SpanProcessorInterface
    {
        $options = $this->config->get('open-telemetry.traces.processors.channel_batch.options', []);

        return new ChannelBatchSpanProcessor(
            exporter: $exporter,
            maxBatchSize: $options['max_batch_size'] ?? ChannelBatchSpanProcessor::DEFAULT_MAX_EXPORT_BATCH_SIZE,
            channelCapacity: $options['channel_capacity'] ?? ChannelBatchSpanProcessor::DEFAULT_CHANNEL_CAPACITY,
            flushInterval: $options['flush_interval'] ?? ChannelBatchSpanProcessor::DEFAULT_FLUSH_INTERVAL,
        );
    }
}
