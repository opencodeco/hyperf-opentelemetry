<?php

declare(strict_types=1);

use Hyperf\OpenTelemetry\Factory\Log\Exporter\OtlpHttpLogExporterFactory;
use Hyperf\OpenTelemetry\Factory\Log\Exporter\StdoutLogExporterFactory;
use Hyperf\OpenTelemetry\Factory\Log\Processor\BatchLogProcessorFactory;
use Hyperf\OpenTelemetry\Factory\Log\Processor\SimpleLogProcessorFactory;
use Hyperf\OpenTelemetry\Factory\Metric\Exporter\OtlpHttpMetricExporterFactory;
use Hyperf\OpenTelemetry\Factory\Metric\Exporter\StdoutMetricExporterFactory;
use Hyperf\OpenTelemetry\Factory\Trace\Exporter\OtlpHttpTraceExporterFactory;
use Hyperf\OpenTelemetry\Factory\Trace\Exporter\StdoutTraceExporterFactory;
use Hyperf\OpenTelemetry\Factory\Trace\Processor\BatchSpanProcessorFactory;
use Hyperf\OpenTelemetry\Factory\Trace\Processor\SimpleSpanProcessorFactory;
use Hyperf\OpenTelemetry\Factory\Trace\Sampler\AlwaysOnSamplerFactory;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SemConv\Attributes\ServiceAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\ServiceIncubatingAttributes;

use function Hyperf\Support\env;

return [
    'resource' => [
        ServiceAttributes::SERVICE_NAME => env('OTEL_APP_NAME', 'hyperf-app'),
        ServiceIncubatingAttributes::SERVICE_NAMESPACE => env('OTEL_APP_NAMESPACE', 'hyperf-opentelemetry'),
        ServiceIncubatingAttributes::SERVICE_INSTANCE_ID => gethostname() ?: uniqid(),
    ],

    'traces' => [
        'enabled' => env('OTEL_TRACES_ENABLED', true),
        'exporter' => env('OTEL_TRACES_EXPORTER', 'otlp_http'),
        'export_interval' => (int) env('OTEL_TRACES_EXPORT_INTERVAL', 5),
        'processor' => env('OTEL_TRACES_PROCESSOR', 'batch'),
        'sampler' => env('OTEL_TRACES_SAMPLER', 'always_on'),
        'uri_mask' => [],
        'exporters' => [
            'otlp_http' => [
                'driver' => OtlpHttpTraceExporterFactory::class,
                'options' => [
                    'endpoint' => env('OTEL_TRACES_ENDPOINT', 'http://localhost:4318/v1/traces'),
                    'content_type' => 'application/x-protobuf',
                    'compression' => TransportFactoryInterface::COMPRESSION_GZIP,
                    'headers' => [],
                    'timeout' => (float) env('OTEL_TRACES_TIMEOUT_SECONDS', 3),
                    'retry' => [
                        'delay_ms' => (int) env('OTEL_TRACES_RETRY_DELAY_MS', 100),
                        'max_retries' => (int) env('OTEL_TRACES_RETRY_MAX', 2),
                    ],
                ],
            ],
            'stdout' => [
                'driver' => StdoutTraceExporterFactory::class,
            ],
        ],
        'processors' => [
            'batch' => [
                'driver' => BatchSpanProcessorFactory::class,
                'options' => [
                    'max_queue_size' => BatchSpanProcessor::DEFAULT_MAX_QUEUE_SIZE,
                    'schedule_delay_ms' => BatchSpanProcessor::DEFAULT_SCHEDULE_DELAY,
                    'export_timeout_ms' => BatchSpanProcessor::DEFAULT_EXPORT_TIMEOUT,
                    'max_export_batch_size' => BatchSpanProcessor::DEFAULT_MAX_EXPORT_BATCH_SIZE,
                    'auto_flush' => false,
                ],
            ],
            'simple' => [
                'driver' => SimpleSpanProcessorFactory::class,
            ],
        ],
        'samplers' => [
            'always_on' => [
                'driver' => AlwaysOnSamplerFactory::class,
            ],
        ],
    ],

    'metrics' => [
        'enabled' => env('OTEL_METRICS_ENABLED', true),
        'exporter' => env('OTEL_METRICS_EXPORTER', 'otlp_http'),
        'export_interval' => (int) env('OTEL_METRICS_EXPORT_INTERVAL', 5),
        'uri_mask' => [],
        'exporters' => [
            'otlp_http' => [
                'driver' => OtlpHttpMetricExporterFactory::class,
                'options' => [
                    'temporality' => Temporality::DELTA,
                    'endpoint' => env('OTEL_METRICS_ENDPOINT', 'http://localhost:4318/v1/metrics'),
                    'content_type' => 'application/x-protobuf',
                    'compression' => TransportFactoryInterface::COMPRESSION_GZIP,
                    'headers' => [],
                    'timeout' => (float) env('OTEL_METRICS_TIMEOUT_SECONDS', 3),
                    'retry' => [
                        'delay_ms' => (int) env('OTEL_METRICS_RETRY_DELAY_MS', 100),
                        'max_retries' => (int) env('OTEL_METRICS_RETRY_MAX', 2),
                    ],
                ],
            ],
            'stdout' => [
                'driver' => StdoutMetricExporterFactory::class,
            ],
        ],
    ],

    'logs' => [
        'enabled' => env('OTEL_LOGS_ENABLED', true),
        'exporter' => env('OTEL_LOGS_EXPORTER', 'stdout'),
        'processor' => env('OTEL_LOGS_PROCESSOR', 'simple'),
        'exporters' => [
            'otlp_http' => [
                'driver' => OtlpHttpLogExporterFactory::class,
                'options' => [
                    'endpoint' => env('OTEL_LOGS_ENDPOINT', 'http://localhost:4318/v1/logs'),
                    'content_type' => 'application/x-protobuf',
                    'compression' => TransportFactoryInterface::COMPRESSION_GZIP,
                    'headers' => [],
                    'timeout' => (float) env('OTEL_LOGS_TIMEOUT_SECONDS', 3),
                    'retry' => [
                        'delay_ms' => (int) env('OTEL_LOGS_RETRY_DELAY_MS', 100),
                        'max_retries' => (int) env('OTEL_LOGS_RETRY_MAX', 2),
                    ],
                ],
            ],
            'stdout' => [
                'driver' => StdoutLogExporterFactory::class,
            ],
        ],
        'processors' => [
            'batch' => [
                'driver' => BatchLogProcessorFactory::class,
                'options' => [
                    'max_queue_size' => BatchLogRecordProcessor::DEFAULT_MAX_QUEUE_SIZE,
                    'schedule_delay_ms' => BatchLogRecordProcessor::DEFAULT_SCHEDULE_DELAY,
                    'export_timeout_ms' => BatchLogRecordProcessor::DEFAULT_EXPORT_TIMEOUT,
                    'max_export_batch_size' => BatchLogRecordProcessor::DEFAULT_MAX_EXPORT_BATCH_SIZE,
                    'auto_flush' => false,
                ],
            ],
            'simple' => [
                'driver' => SimpleLogProcessorFactory::class,
            ],
        ],
    ],

    'instrumentation' => [
        'enabled' => env('OTEL_INSTRUMENTATION_ENABLED', true),

        'features' => [
            'client_request' => [
                'traces' => env('OTEL_INSTRUMENTATION_FEATURES_CLIENT_REQUEST_TRACES', true),
                'metrics' => env('OTEL_INSTRUMENTATION_FEATURES_CLIENT_REQUEST_METRICS', true),
                'options' => [
                    'headers' => [
                        'request' => ['x-*', 'content*', 'trace*', 'user*', 'consumer*', 'host'],
                        'response' => ['*'],
                    ],
                    'ignore_paths' => [
                        // '/^\/health$/',
                    ],
                ],
            ],
            'db_query' => [
                'traces' => env('OTEL_INSTRUMENTATION_FEATURES_DB_QUERY_TRACES', true),
                'metrics' => env('OTEL_INSTRUMENTATION_FEATURES_DB_QUERY_METRICS', true),
                'options' => [
                    'combine_sql_and_bindings' => false,
                ],
            ],
            'guzzle' => [
                'traces' => env('OTEL_INSTRUMENTATION_FEATURES_GUZZLE_TRACES', true),
                'metrics' => env('OTEL_INSTRUMENTATION_FEATURES_GUZZLE_METRICS', true),
                'options' => [
                    'headers' => [
                        'request' => ['*'],
                        'response' => ['*'],
                    ],
                ],
            ],
            'redis' => [
                'traces' => env('OTEL_INSTRUMENTATION_FEATURES_REDIS_TRACES', true),
                'metrics' => env('OTEL_INSTRUMENTATION_FEATURES_REDIS_METRICS', true),
            ],
            'aws_sqs' => [
                'traces' => env('OTEL_INSTRUMENTATION_FEATURES_SQS_TRACES', true),
                'metrics' => env('OTEL_INSTRUMENTATION_FEATURES_SQS_METRICS', true),
            ],
            'aws_sns' => [
                'traces' => env('OTEL_INSTRUMENTATION_FEATURES_SNS_TRACES', true),
                'metrics' => env('OTEL_INSTRUMENTATION_FEATURES_SNS_METRICS', true),
            ],
            'aws_dynamodb' => [
                'traces' => env('OTEL_INSTRUMENTATION_FEATURES_DYNAMODB_TRACES', true),
                'metrics' => env('OTEL_INSTRUMENTATION_FEATURES_DYNAMODB_METRICS', true),
            ],
        ],
    ],
];
