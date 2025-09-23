<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Aspect\Aws;

use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\Exception;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Attributes\DbAttributes;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\DbIncubatingAttributes as DbIncubatingAttr;
use OpenTelemetry\SemConv\Metrics\DbMetrics;
use Hyperf\OpenTelemetry\Aspect\AbstractAspect;
use Throwable;

class DynamoDbClientAspect extends AbstractAspect
{
    public array $classes = [
        'Aws\AwsClientTrait::execute',
    ];

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $instance = $proceedingJoinPoint->getInstance();
        $service = $instance?->getApi()->getMetadata()['serviceId'] ?? '';

        if (! $this->isTelemetryEnabled() || $service != 'DynamoDB') {
            return $proceedingJoinPoint->process();
        }

        $command = $proceedingJoinPoint->arguments['keys']['command'] ?? null;
        $operation = $command?->getName() ?? '';
        $input = $command?->toArray() ?? [];

        $table = $input['TableName'] ?? 'unknown';

        $scope = null;
        $start = microtime(true);
        $metricErrors = [];

        if ($this->isTracingEnabled) {
            $scope = $this->instrumentation->startSpan(
                name: 'DynamoDB ' . $operation,
                spanKind: SpanKind::KIND_CLIENT,
                attributes: [
                    DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttr::DB_SYSTEM_NAME_VALUE_AWS_DYNAMODB,
                    DbAttributes::DB_OPERATION_NAME => $operation,
                    DbAttributes::DB_COLLECTION_NAME => $table,
                    'db.system' => DbIncubatingAttr::DB_SYSTEM_NAME_VALUE_AWS_DYNAMODB,
                    'db.operation' => $operation,
                    'db.sql.table' => $table,
                    'aws.dynamodb.table_names' => $table,
                ]
            );
        }

        try {
            $result = $proceedingJoinPoint->process();

            $scope?->setStatus(StatusCode::STATUS_OK);

            return $result;
        } catch (Throwable $e) {
            $scope?->recordException($e);

            $metricErrors = [
                ErrorAttributes::ERROR_TYPE => get_class($e),
            ];

            throw $e;
        } finally {
            $scope?->end();

            if ($this->isMetricsEnabled) {
                $duration = (microtime(true) - $start) * 1000;

                $this->instrumentation->meter()
                    ->createHistogram(DbMetrics::DB_CLIENT_OPERATION_DURATION, 'ms')
                    ->record(
                        $duration,
                        array_merge(
                            $metricErrors,
                            [
                                DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttr::DB_SYSTEM_NAME_VALUE_AWS_DYNAMODB,
                                DbAttributes::DB_OPERATION_NAME => $operation,
                                DbAttributes::DB_COLLECTION_NAME => $table,
                                DbAttributes::DB_QUERY_SUMMARY => ! empty($table)
                                    ? "{$operation} {$table}"
                                    : $operation,
                            ]
                        )
                    );
            }
        }
    }

    protected function featureName(): string
    {
        return 'aws_dynamodb';
    }
}
