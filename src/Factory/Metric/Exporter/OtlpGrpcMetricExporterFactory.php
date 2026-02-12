<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Metric\Exporter;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Transport\SwooleGrpcTransportFactory;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;

class OtlpGrpcMetricExporterFactory implements MetricExporterFactoryInterface
{
    private const GRPC_METHOD = '/opentelemetry.proto.collector.metrics.v1.MetricsService/Export';

    public function __construct(
        protected readonly ConfigInterface $config,
    ) {
    }

    public function make(): MetricExporterInterface
    {
        $options = $this->config->get('open-telemetry.metrics.exporters.otlp_grpc.options', []);

        $endpoint = rtrim($options['endpoint'], '/') . self::GRPC_METHOD;

        return new MetricExporter(
            transport: (new SwooleGrpcTransportFactory())->create(
                endpoint: $endpoint,
                contentType: 'application/x-protobuf',
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
