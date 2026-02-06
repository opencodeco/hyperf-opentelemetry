<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Support;

/**
 * Explicit bucket boundaries for OpenTelemetry histograms.
 *
 * These boundaries follow OpenTelemetry semantic conventions.
 *
 * @see https://opentelemetry.io/docs/specs/semconv/http/http-metrics/
 * @see https://opentelemetry.io/docs/specs/semconv/db/database-metrics/
 */
final class MetricBoundaries
{
    /**
     * Explicit bucket boundaries for HTTP duration histograms.
     * Values are in seconds as per OpenTelemetry semantic conventions.
     *
     * Used for:
     * - http.server.request.duration
     * - http.client.request.duration
     *
     * @see https://opentelemetry.io/docs/specs/semconv/http/http-metrics/#metric-httpserverrequestduration
     * @see https://opentelemetry.io/docs/specs/semconv/http/http-metrics/#metric-httpclientrequestduration
     */
    public const HTTP_DURATION = [
        0.005,
        0.01,
        0.025,
        0.05,
        0.075,
        0.1,
        0.25,
        0.5,
        0.75,
        1,
        2.5,
        5,
        7.5,
        10,
    ];

    /**
     * Explicit bucket boundaries for database operation duration histograms.
     * Values are in seconds as per OpenTelemetry semantic conventions.
     *
     * Used for:
     * - db.client.operation.duration
     *
     * @see https://opentelemetry.io/docs/specs/semconv/db/database-metrics/#metric-dbclientoperationduration
     */
    public const DB_DURATION = [
        0.001,
        0.005,
        0.01,
        0.05,
        0.1,
        0.5,
        1,
        5,
        10,
    ];
}
