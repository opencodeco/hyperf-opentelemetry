<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Metric\Exporter;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;

class OtlpHttpMetricExporterFactory implements MetricExporterFactoryInterface
{
    public function __construct(
        protected readonly ConfigInterface $config,
    ) {
    }

    public function make(): MetricExporterInterface
    {
        $options = $this->config->get('open-telemetry.metrics.exporters.otlp_http.options', []);

        return new MetricExporter(
            transport: (new OtlpHttpTransportFactory())->create(
                endpoint: $options['endpoint'],
                contentType: $options['content_type'] ?? 'application/x-protobuf',
                headers: $options['headers'] ?? [],
                compression: $options['compression'] ?? TransportFactoryInterface::COMPRESSION_GZIP,
                timeout: $options['timeout'] ?? 10,
                retryDelay: $options['retry_delay'] ?? 100,
                maxRetries: $options['max_retries'] ?? 3,
            ),
            temporality: $options['temporality'] ?? Temporality::DELTA,
        );
    }
}
