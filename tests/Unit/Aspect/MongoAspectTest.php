<?php

declare(strict_types=1);

namespace Tests\Unit\Aspect;

use Exception;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\OpenTelemetry\Aspect\MongoAspect;
use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Support\SpanScope;
use Hyperf\OpenTelemetry\Switcher;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Attributes\DbAttributes;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\DbIncubatingAttributes;
use OpenTelemetry\SemConv\Metrics\DbMetrics;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class MongoAspectTest extends TestCase
{
    private ConfigInterface $config;

    private Instrumentation $instrumentation;

    private Switcher $switcher;

    private ProceedingJoinPoint $proceedingJoinPoint;

    private object $mongoCollection;

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

        $this->instrumentation->method('meter')->willReturn($this->meter);

        if (!class_exists('Hyperf\GoTask\MongoClient\Collection')) {
            eval('
                namespace Hyperf\GoTask\MongoClient {
                    class Collection {
                        private string $collection = "users";
                    }
                }
            ');
        }

        $this->mongoCollection = new \Hyperf\GoTask\MongoClient\Collection();

        $reflection = new \ReflectionProperty(\Hyperf\GoTask\MongoClient\Collection::class, 'collection');
        $reflection->setAccessible(true);
        $reflection->setValue($this->mongoCollection, 'users');

        $this->switcher->method('isTracingEnabled')->willReturn(true);
        $this->switcher->method('isMetricsEnabled')->willReturn(true);

        $this->proceedingJoinPoint->method('getInstance')->willReturn($this->mongoCollection);
    }

    public function testProcessWhenTelemetryIsDisabled(): void
    {
        $switcher = $this->createMock(Switcher::class);
        $switcher->method('isTracingEnabled')->willReturn(false);
        $switcher->method('isMetricsEnabled')->willReturn(false);

        $aspect = new MongoAspect(
            $this->config,
            $this->instrumentation,
            $switcher
        );

        $this->proceedingJoinPoint->methodName = 'find';

        $expectedResult = [['_id' => '123', 'name' => 'John']];
        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willReturn($expectedResult);

        $this->instrumentation->expects($this->never())->method('startSpan');
        $this->meter->expects($this->never())->method('createHistogram');

        $result = $aspect->process($this->proceedingJoinPoint);

        $this->assertEquals($expectedResult, $result);
    }

    public function testProcessWithIgnoredMethod(): void
    {
        $aspect = new MongoAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $this->proceedingJoinPoint->methodName = 'makePayload';

        $expectedResult = ['key' => 'value'];
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
        $this->proceedingJoinPoint->methodName = 'find';

        $aspect = new MongoAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with(
                $this->equalTo('mongodb FIND users'),
                $this->equalTo(SpanKind::KIND_CLIENT),
                $this->equalTo([
                    DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_MONGODB,
                    DbAttributes::DB_COLLECTION_NAME => 'users',
                    DbAttributes::DB_OPERATION_NAME => 'FIND',
                    'db.system' => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_MONGODB,
                    'db.operation' => 'FIND',
                    'db.collection' => 'users',
                ])
            )
            ->willReturn($this->spanScope);

        $this->spanScope->expects($this->once())
            ->method('setStatus')
            ->with(StatusCode::STATUS_OK);

        $this->spanScope->expects($this->once())->method('end');

        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->with(DbMetrics::DB_CLIENT_OPERATION_DURATION, 'ms')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                $this->isType('float'),
                $this->equalTo([
                    DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_MONGODB,
                    DbAttributes::DB_OPERATION_NAME => 'FIND',
                ])
            );

        $expectedResult = [['_id' => '123', 'name' => 'John']];
        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willReturn($expectedResult);

        $result = $aspect->process($this->proceedingJoinPoint);

        $this->assertEquals($expectedResult, $result);
    }

    public function testProcessWithDifferentMethods(): void
    {
        $methods = ['insertOne', 'updateOne', 'deleteOne', 'aggregate'];

        foreach ($methods as $method) {
            $instrumentation = $this->createMock(Instrumentation::class);
            $instrumentation->method('meter')->willReturn($this->meter);
            
            $spanScope = $this->createMock(SpanScope::class);
            $spanScope->expects($this->once())->method('setStatus');
            $spanScope->expects($this->once())->method('end');

            $aspect = new MongoAspect(
                $this->config,
                $instrumentation,
                $this->switcher
            );

            $proceedingJoinPoint = $this->createMock(ProceedingJoinPoint::class);
            $proceedingJoinPoint->methodName = $method;
            $proceedingJoinPoint->method('getInstance')->willReturn($this->mongoCollection);

            $expectedOperation = strtoupper($method);

            $instrumentation
                ->expects($this->once())
                ->method('startSpan')
                ->with(
                    $this->equalTo("mongodb {$expectedOperation} users"),
                    $this->anything(),
                    $this->callback(function ($attributes) use ($expectedOperation) {
                        return $attributes[DbAttributes::DB_OPERATION_NAME] === $expectedOperation;
                    })
                )
                ->willReturn($spanScope);

            $proceedingJoinPoint->expects($this->once())
                ->method('process')
                ->willReturn([]);

            $aspect->process($proceedingJoinPoint);
        }
    }

    public function testProcessWithException(): void
    {
        $this->proceedingJoinPoint->methodName = 'insertOne';

        $aspect = new MongoAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with('mongodb INSERTONE users')
            ->willReturn($this->spanScope);

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
                    DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_MONGODB,
                    DbAttributes::DB_OPERATION_NAME => 'INSERTONE',
                ]
            );

        $exception = new Exception('MongoDB error');

        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willThrowException($exception);

        $this->spanScope->expects($this->once())
            ->method('recordException')
            ->with($exception);

        $this->spanScope->expects($this->once())->method('end');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('MongoDB error');

        $aspect->process($this->proceedingJoinPoint);
    }

    public function testProcessWithDifferentCollectionName(): void
    {
        $ordersCollection = new \Hyperf\GoTask\MongoClient\Collection();
        $reflection = new \ReflectionProperty(\Hyperf\GoTask\MongoClient\Collection::class, 'collection');
        $reflection->setAccessible(true);
        $reflection->setValue($ordersCollection, 'orders');

        $proceedingJoinPoint = $this->createMock(ProceedingJoinPoint::class);
        $proceedingJoinPoint->methodName = 'find';
        $proceedingJoinPoint->method('getInstance')->willReturn($ordersCollection);

        $aspect = new MongoAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with(
                $this->equalTo('mongodb FIND orders'),
                $this->anything(),
                $this->callback(function ($attributes) {
                    return $attributes[DbAttributes::DB_COLLECTION_NAME] === 'orders';
                })
            )
            ->willReturn($this->spanScope);

        $proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willReturn([]);

        $aspect->process($proceedingJoinPoint);
    }

    public function testFeatureName(): void
    {
        $aspect = new MongoAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $reflection = new \ReflectionClass($aspect);
        $method = $reflection->getMethod('featureName');
        $method->setAccessible(true);

        $result = $method->invoke($aspect);

        $this->assertEquals('db_query', $result);
    }

    public function testProcessWithTracingDisabled(): void
    {
        $switcher = $this->createMock(Switcher::class);
        $switcher->method('isTracingEnabled')->willReturn(false);
        $switcher->method('isMetricsEnabled')->willReturn(true);

        $aspect = new MongoAspect(
            $this->config,
            $this->instrumentation,
            $switcher
        );

        $this->proceedingJoinPoint->methodName = 'find';

        $this->instrumentation->expects($this->never())->method('startSpan');

        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->willReturn($this->histogram);

        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willReturn([]);

        $aspect->process($this->proceedingJoinPoint);
    }

    public function testProcessWithMetricsDisabled(): void
    {
        $switcher = $this->createMock(Switcher::class);
        $switcher->method('isTracingEnabled')->willReturn(true);
        $switcher->method('isMetricsEnabled')->willReturn(false);

        $aspect = new MongoAspect(
            $this->config,
            $this->instrumentation,
            $switcher
        );

        $this->proceedingJoinPoint->methodName = 'find';

        $this->instrumentation->expects($this->once())
            ->method('startSpan')
            ->willReturn($this->spanScope);

        $this->meter->expects($this->never())->method('createHistogram');

        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willReturn([]);

        $aspect->process($this->proceedingJoinPoint);
    }
}
