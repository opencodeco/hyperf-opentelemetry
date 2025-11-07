<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Trace\Exporter;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\Contrib\Grpc\GrpcTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;

class OtlpGrpcTraceExporterFactory implements TraceExporterFactoryInterface
{
    public function __construct(
        protected readonly ConfigInterface $config,
    ) {
    }

    public function make(): SpanExporterInterface
    {
        $options = $this->config->get('open-telemetry.traces.exporters.otlp_grpc.options', []);

        return new SpanExporter(
            (new GrpcTransportFactory())->create(
                endpoint: $options['endpoint'],
                contentType: 'application/x-protobuf',
                headers: $options['headers'] ?? [],
                compression: $options['compression'] ?? TransportFactoryInterface::COMPRESSION_GZIP,
                timeout: $options['timeout'] ?? 10,
                retryDelay: $options['retry_delay'] ?? 100,
                maxRetries: $options['max_retries'] ?? 3,
            )
        );
    }
}
