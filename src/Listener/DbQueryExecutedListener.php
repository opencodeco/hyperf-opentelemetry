<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Listener;

use Hyperf\Collection\Arr;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Stringable\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\Attributes\DbAttributes;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use OpenTelemetry\SemConv\Attributes\ServerAttributes;
use OpenTelemetry\SemConv\Metrics\DbMetrics;
use Hyperf\OpenTelemetry\Support\AbstractInstrumenter;
use Throwable;

class DbQueryExecutedListener extends AbstractInstrumenter implements ListenerInterface
{
    public function listen(): array
    {
        return [
            QueryExecuted::class,
        ];
    }

    public function process(object $event): void
    {
        match ($event::class) {
            QueryExecuted::class => $this->onQueryExecuted($event),
            default => null,
        };
    }

    protected function featureName(): string
    {
        return 'db_query';
    }

    protected function onQueryExecuted(QueryExecuted $event): void
    {
        if (! $this->isTelemetryEnabled()) {
            return;
        }

        $nowInNs = (int) (microtime(true) * 1E9);

        $sql = $this->config->get(
            'open-telemetry.instrumentation.features.db_query.options.combine_sql_and_bindings',
            false
        ) ? $this->combineSqlAndBindings($event) : $event->sql;

        $operation = Str::upper(Str::before($event->sql, ' '));
        $table = $this->extractTableName($sql);
        $driver = $this->getDriverName($event);
        $spanName = $table ? "{$driver} {$operation} {$table}" : "{$driver} {$operation}";

        if ($this->isTracingEnabled) {
            $scope = $this->instrumentation->startSpan(
                name: $spanName,
                spanKind: SpanKind::KIND_CLIENT,
                attributes: [
                    DbAttributes::DB_SYSTEM_NAME => $driver,
                    DbAttributes::DB_COLLECTION_NAME => $table,
                    DbAttributes::DB_NAMESPACE => $event->connection->getDatabaseName(),
                    DbAttributes::DB_OPERATION_NAME => Str::upper($operation),
                    DbAttributes::DB_QUERY_TEXT => $sql,
                    ServerAttributes::SERVER_ADDRESS => $event->connection->getConfig('host'),
                    ServerAttributes::SERVER_PORT => $event->connection->getConfig('port'),
                    'db.system' => $driver,
                    'db.operation' => Str::upper($operation),
                    'db.sql.table' => $table,
                ],
                startTimestamp: $this->calculateQueryStartTime($nowInNs, $event->time)
            );

            if ($event->result instanceof Throwable) {
                $scope->recordException($event->result);
            }

            $scope->end();
        }

        if ($this->isMetricsEnabled) {
            $metricErrors = [];

            if ($event->result instanceof Throwable) {
                $metricErrors = [
                    ErrorAttributes::ERROR_TYPE => get_class($event->result),
                ];
            }

            $this->instrumentation->meter()
                ->createHistogram(DbMetrics::DB_CLIENT_OPERATION_DURATION, 'ms')
                ->record(
                    $event->time,
                    array_merge(
                        $metricErrors,
                        [
                            DbAttributes::DB_SYSTEM_NAME => $driver,
                            DbAttributes::DB_COLLECTION_NAME => $table,
                            DbAttributes::DB_NAMESPACE => $event->connection->getDatabaseName(),
                            DbAttributes::DB_OPERATION_NAME => Str::upper($operation),
                            DbAttributes::DB_QUERY_SUMMARY => $table ? "{$operation} {$table}" : $operation,
                        ]
                    )
                );
        }
    }

    protected function combineSqlAndBindings(QueryExecuted $event): string
    {
        $sql = $event->sql;
        if (! Arr::isAssoc($event->bindings)) {
            foreach ($event->bindings as $value) {
                $sql = Str::replaceFirst('?', "'{$value}'", $sql);
            }
        }

        return $sql;
    }

    private function getDriverName(QueryExecuted $event): string
    {
        return match ($event->connection->getDriverName()) {
            'mysql' => DbAttributes::DB_SYSTEM_NAME_VALUE_MYSQL,
            'pgsql' => DbAttributes::DB_SYSTEM_NAME_VALUE_POSTGRESQL,
            'sqlsrv' => DbAttributes::DB_SYSTEM_NAME_VALUE_MICROSOFT_SQL_SERVER,
            'sqlite' => 'sqlite',
            default => $event->connection->getDriverName(),
        };
    }

    private function extractTableName(string $sql): ?string
    {
        if (preg_match('/(?:FROM|INTO|UPDATE)\s+`?(\w+)`?/i', $sql, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function calculateQueryStartTime(int $nowInNs, float $queryTimeMs): int
    {
        return (int) ($nowInNs - ($queryTimeMs * 1E6));
    }
}
