<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Aspect;

use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\Exception;
use Hyperf\OpenTelemetry\Support\MetricBoundaries;
use Hyperf\Stringable\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\Attributes\DbAttributes;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\DbIncubatingAttributes;
use OpenTelemetry\SemConv\Metrics\DbMetrics;
use Throwable;

class RedisAspect extends AbstractAspect
{
    public array $classes = [
        'Hyperf\Redis\Redis::__call',
    ];

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        if (! $this->isTelemetryEnabled()) {
            return $proceedingJoinPoint->process();
        }

        $args = $proceedingJoinPoint->arguments['keys'];
        $command = Str::lower($args['name']);
        $commandFull = $command . ' ' . $this->buildCommandArguments($args['arguments']);
        $poolName = (fn () => $this->poolName ?? 'default')->call($proceedingJoinPoint->getInstance());

        $scope = null;
        $start = microtime(true);
        $metricErrors = [];

        if ($this->isTracingEnabled) {
            $scope = $this->instrumentation->startSpan(
                name: Str::upper($command),
                spanKind: SpanKind::KIND_CLIENT,
                attributes: [
                    DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_REDIS,
                    DbAttributes::DB_OPERATION_NAME => Str::upper($command),
                    DbAttributes::DB_QUERY_TEXT => Str::limit($commandFull, 512),
                ]
            );
        }

        try {
            $result = $proceedingJoinPoint->process();
        } catch (Throwable $e) {
            $scope?->recordException($e);

            $metricErrors = [
                ErrorAttributes::ERROR_TYPE => get_class($e),
            ];

            throw $e;
        } finally {
            $scope?->end();

            if ($this->isMetricsEnabled) {
                $durationInSeconds = microtime(true) - $start;

                $this->instrumentation->meter()
                    ->createHistogram(
                        DbMetrics::DB_CLIENT_OPERATION_DURATION,
                        's',
                        'Duration of database client operations.',
                        ['ExplicitBucketBoundaries' => MetricBoundaries::DB_DURATION]
                    )
                    ->record(
                        $durationInSeconds,
                        array_merge(
                            $metricErrors,
                            [
                                DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_REDIS,
                                DbAttributes::DB_OPERATION_NAME => Str::upper($command),
                            ]
                        )
                    );
            }
        }

        return $result;
    }

    protected function featureName(): string
    {
        return 'redis';
    }

    private function buildCommandArguments(array $args): string
    {
        $callback = static function (array $args) use (&$callback) {
            $result = '';
            foreach ($args as $arg) {
                if (is_array($arg)) {
                    $result .= $callback($arg);
                } elseif (! is_object($arg)) {
                    $result .= $arg . ' ';
                }
            }

            return trim($result);
        };

        return $callback($args);
    }
}
