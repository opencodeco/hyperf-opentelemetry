<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Log\Exporter;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;

class OtlpHttpLogExporterFactory implements LogExporterFactoryInterface
{
    public function __construct(
        protected readonly ConfigInterface $config,
    ) {
    }

    public function make(): LogRecordExporterInterface
    {
        $options = $this->config->get('open-telemetry.logs.exporters.otlp_http.options', []);

        return new LogsExporter(
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
