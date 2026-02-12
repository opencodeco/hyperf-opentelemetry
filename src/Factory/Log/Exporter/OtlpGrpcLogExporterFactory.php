<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Log\Exporter;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Transport\SwooleGrpcTransportFactory;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;

class OtlpGrpcLogExporterFactory implements LogExporterFactoryInterface
{
    private const GRPC_METHOD = '/opentelemetry.proto.collector.logs.v1.LogsService/Export';

    public function __construct(
        protected readonly ConfigInterface $config,
    ) {
    }

    public function make(): LogRecordExporterInterface
    {
        $options = $this->config->get('open-telemetry.logs.exporters.otlp_grpc.options', []);

        $endpoint = rtrim($options['endpoint'] ?? 'http://localhost:4317', '/') . self::GRPC_METHOD;

        return new LogsExporter(
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
