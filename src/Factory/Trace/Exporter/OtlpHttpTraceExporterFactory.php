<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Trace\Exporter;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;

class OtlpHttpTraceExporterFactory implements TraceExporterFactoryInterface
{
    public function __construct(
        protected readonly ConfigInterface $config,
    ) {
    }

    public function make(): SpanExporterInterface
    {
        $options = $this->config->get('open-telemetry.traces.exporters.otlp_http.options', []);

        return new SpanExporter(
            (new OtlpHttpTransportFactory())->create(
                endpoint: $options['endpoint'],
                contentType: $options['content_type'] ?? 'application/x-protobuf',
                headers: $options['headers'] ?? [],
                compression: $options['compression'] ?? TransportFactoryInterface::COMPRESSION_GZIP,
                timeout: $options['timeout'] ?? 10,
                retryDelay: $options['retry_delay'] ?? 100,
                maxRetries: $options['max_retries'] ?? 3,
            )
        );
    }
}
