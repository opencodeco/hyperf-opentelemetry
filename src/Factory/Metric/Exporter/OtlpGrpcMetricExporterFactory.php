<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Metric\Exporter;

use Hyperf\Contract\ConfigInterface;
use InvalidArgumentException;
use OpenTelemetry\Contrib\Grpc\GrpcTransportFactory;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;

class OtlpGrpcMetricExporterFactory implements MetricExporterFactoryInterface
{
    public function __construct(
        protected readonly ConfigInterface $config,
    ) {
    }

    public function make(): MetricExporterInterface
    {
        if (! extension_loaded('grpc')) {
            throw new InvalidArgumentException('The gRPC extension is not loaded.');
        }

        $options = $this->config->get('open-telemetry.metrics.exporters.otlp_grpc.options', []);

        return new MetricExporter(
            transport: (new GrpcTransportFactory())->create(
                endpoint: $options['endpoint'],
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
