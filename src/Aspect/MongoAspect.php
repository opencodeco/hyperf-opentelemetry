<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Aspect;

use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Stringable\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Attributes\DbAttributes;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\DbIncubatingAttributes;
use OpenTelemetry\SemConv\Metrics\DbMetrics;
use ReflectionProperty;
use Throwable;

class MongoAspect extends AbstractAspect
{
    public array $classes = [
        'Hyperf\GoTask\MongoClient\Collection',
    ];

    public array $annotations = [];

    protected array $ignoredMethods = [
        'makePayload',
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        if (! $this->isTelemetryEnabled()) {
            return $proceedingJoinPoint->process();
        }

        if (in_array($proceedingJoinPoint->methodName, $this->ignoredMethods)) {
            return $proceedingJoinPoint->process();
        }

        $method = $proceedingJoinPoint->methodName;

        $operation = Str::upper($method);
        $collection = $this->getCollectionName($proceedingJoinPoint);
        $driver = DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_MONGODB;

        $scope = null;
        $start = microtime(true);
        $metricErrors = [];

        if ($this->isTracingEnabled) {
            $scope = $this->instrumentation->startSpan(
                name: "{$driver} {$operation} {$collection}",
                spanKind: SpanKind::KIND_CLIENT,
                attributes: [
                    DbAttributes::DB_SYSTEM_NAME => $driver,
                    DbAttributes::DB_COLLECTION_NAME => $collection,
                    DbAttributes::DB_OPERATION_NAME => $operation,
                    'db.system' => $driver,
                    'db.operation' => $operation,
                    'db.collection' => $collection,
                ],
            );
        }

        try {
            $result = $proceedingJoinPoint->process();
            $scope?->setStatus(StatusCode::STATUS_OK);
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
                        array_merge($metricErrors, [
                            DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_MONGODB,
                            DbAttributes::DB_OPERATION_NAME => $operation,
                        ])
                    );
            }
        }

        return $result;
    }

    protected function featureName(): string
    {
        return 'db_query';
    }

    private function getCollectionName(ProceedingJoinPoint $proceedingJoinPoint): string
    {
        $collection = $proceedingJoinPoint->getInstance();

        $property = new ReflectionProperty('Hyperf\GoTask\MongoClient\Collection', 'collection');

        return $property->getValue($collection);
    }
}
