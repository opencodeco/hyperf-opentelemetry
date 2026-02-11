<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Trace\Exporter;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Transport\SwooleGrpcTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;

class OtlpGrpcTraceExporterFactory implements TraceExporterFactoryInterface
{
    private const GRPC_METHOD = '/opentelemetry.proto.collector.trace.v1.TraceService/Export';

    public function __construct(
        protected readonly ConfigInterface $config,
    ) {
    }

    public function make(): SpanExporterInterface
    {
        $options = $this->config->get('open-telemetry.traces.exporters.otlp_grpc.options', []);

        $endpoint = rtrim($options['endpoint'], '/') . self::GRPC_METHOD;

        return new SpanExporter(
            (new SwooleGrpcTransportFactory())->create(
                endpoint: $endpoint,
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
