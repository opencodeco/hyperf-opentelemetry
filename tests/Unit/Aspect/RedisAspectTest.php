<?php

declare(strict_types=1);

namespace Tests\Unit\Aspect;

use Exception;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Attributes\DbAttributes;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\DbIncubatingAttributes;
use OpenTelemetry\SemConv\Metrics\DbMetrics;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Aspect\RedisAspect;
use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Support\SpanScope;
use Hyperf\OpenTelemetry\Switcher;

/**
 * @internal
 */
class RedisAspectTest extends TestCase
{
    private ConfigInterface $config;

    private Instrumentation $instrumentation;

    private Switcher $switcher;

    private ProceedingJoinPoint $proceedingJoinPoint;

    private SpanScope $spanScope;

    private MeterInterface $meter;

    private HistogramInterface $histogram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(ConfigInterface::class);
        $this->instrumentation = $this->createMock(Instrumentation::class);
        $this->switcher = $this->createMock(Switcher::class);
        $this->proceedingJoinPoint = $this->createMock(ProceedingJoinPoint::class);
        $this->spanScope = $this->createMock(SpanScope::class);
        $this->meter = $this->createMock(MeterInterface::class);
        $this->histogram = $this->createMock(HistogramInterface::class);

        $this->switcher->method('isTracingEnabled')->willReturn(true);
        $this->switcher->method('isMetricsEnabled')->willReturn(true);

        $this->instrumentation->method('meter')->willReturn($this->meter);
    }

    public function testProcessWhenTelemetryIsDisabled(): void
    {
        $aspect = new RedisAspect(
            $this->config,
            $this->instrumentation,
            $this->createMock(Switcher::class),
        );

        $expectedResult = 'result';
        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willReturn($expectedResult);

        $this->instrumentation->expects($this->never())->method('startSpan');
        $this->meter->expects($this->never())->method('createHistogram');

        $result = $aspect->process($this->proceedingJoinPoint);

        $this->assertEquals($expectedResult, $result);
    }

    public function testProcess(): void
    {
        $this->proceedingJoinPoint->arguments = [
            'keys' => [
                'name' => 'GET',
                'arguments' => ['user:123'],
            ],
        ];

        $aspect = new RedisAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher,
        );

        $this->proceedingJoinPoint->method('getInstance')->willReturn(new class {});

        // Span
        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with(
                $this->equalTo('Redis GET'),
                $this->equalTo(SpanKind::KIND_CLIENT),
                [
                    DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_REDIS,
                    DbAttributes::DB_OPERATION_NAME => 'GET',
                    DbAttributes::DB_QUERY_TEXT => 'get user:123',
                    'db.system' => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_REDIS,
                    'db.operation.pool' => 'GET',
                    'redis.pool' => 'default',
                ]
            )
            ->willReturn($this->spanScope);

        $this->spanScope->expects($this->once())
            ->method('setStatus')
            ->with(StatusCode::STATUS_OK);

        $this->spanScope->expects($this->once())->method('end');

        // Metric
        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->with(DbMetrics::DB_CLIENT_OPERATION_DURATION, 'ms')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                $this->isType('float'),
                $this->equalTo([
                    DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_REDIS,
                    DbAttributes::DB_OPERATION_NAME => 'GET',
                ])
            );

        $expectedResult = 'cached_value';
        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willReturn($expectedResult);

        $result = $aspect->process($this->proceedingJoinPoint);
        $this->assertEquals($expectedResult, $result);
    }

    public function testProcessWithException(): void
    {
        $this->proceedingJoinPoint->arguments = [
            'keys' => [
                'name' => 'SET',
                'arguments' => ['user:123', 'data', 'EX', 3600],
            ],
        ];

        $exception = new Exception('Redis connection failed');

        $aspect = new RedisAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher,
        );

        $this->proceedingJoinPoint->method('getInstance')->willReturn(new class {
            public $poolName = 'semaphore';
        });

        // Span
        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with(
                $this->equalTo('Redis SET'),
                $this->equalTo(SpanKind::KIND_CLIENT),
                [
                    DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_REDIS,
                    DbAttributes::DB_OPERATION_NAME => 'SET',
                    DbAttributes::DB_QUERY_TEXT => 'set user:123 data EX 3600',
                    'db.system' => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_REDIS,
                    'db.operation.pool' => 'SET',
                    'redis.pool' => 'semaphore',
                ]
            )
            ->willReturn($this->spanScope);

        $this->spanScope->expects($this->once())
            ->method('recordException')
            ->with($exception);

        $this->spanScope->expects($this->once())->method('end');

        // Metric
        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->with(DbMetrics::DB_CLIENT_OPERATION_DURATION, 'ms')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                $this->isType('float'),
                [
                    ErrorAttributes::ERROR_TYPE => Exception::class,
                    DbAttributes::DB_OPERATION_NAME => 'SET',
                    DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_REDIS,
                ]
            );

        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willThrowException($exception);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Redis connection failed');

        $aspect->process($this->proceedingJoinPoint);
    }

    public function testProcessWithComplexArguments(): void
    {
        $this->proceedingJoinPoint->arguments = [
            'keys' => [
                'name' => 'HMSET',
                'arguments' => [
                    'user:123',
                    ['name' => 'John', 'age' => 30, 'city' => 'São Paulo'],
                ],
            ],
        ];

        $this->switcher->method('isTracingEnabled')->willReturn(true);

        $aspect = new RedisAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher,
        );

        $this->proceedingJoinPoint->method('getInstance')->willReturn(new class {});

        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with(
                $this->equalTo('Redis HMSET'),
                $this->equalTo(SpanKind::KIND_CLIENT),
                [
                    DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_REDIS,
                    DbAttributes::DB_OPERATION_NAME => 'HMSET',
                    DbAttributes::DB_QUERY_TEXT => 'hmset user:123 John 30 São Paulo',
                    'db.system' => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_REDIS,
                    'redis.pool' => 'default',
                    'db.operation.pool' => 'HMSET',
                ]
            )
            ->willReturn($this->spanScope);

        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willReturn('OK');

        $aspect->process($this->proceedingJoinPoint);
    }
}
