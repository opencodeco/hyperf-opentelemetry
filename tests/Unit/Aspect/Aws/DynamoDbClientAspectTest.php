<?php

declare(strict_types=1);

namespace Tests\Unit\Aspect\Aws;

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
use Hyperf\OpenTelemetry\Aspect\Aws\DynamoDbClientAspect;
use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Support\SpanScope;
use Hyperf\OpenTelemetry\Switcher;

/**
 * @internal
 */
class DynamoDbClientAspectTest extends TestCase
{
    private ConfigInterface $config;

    private Instrumentation $instrumentation;

    private Switcher $switcher;

    private ProceedingJoinPoint $proceedingJoinPoint;

    private object $awsClient;

    private object $apiService;

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

        $this->apiService = new class {
            public array $metadata = ['serviceId' => 'DynamoDB'];

            public function getMetadata()
            {
                return $this->metadata;
            }

            public function setMetadata(array $metadata)
            {
                $this->metadata = $metadata;
            }
        };

        $this->awsClient = new class($this->apiService) {
            public function __construct(private object $service)
            {
            }

            public function getApi()
            {
                return $this->service;
            }
        };

        $this->switcher->method('isTracingEnabled')->willReturn(true);
        $this->switcher->method('isMetricsEnabled')->willReturn(true);

        $this->proceedingJoinPoint->method('getInstance')->willReturn($this->awsClient);
    }

    public function testProcessWhenTelemetryIsDisabled(): void
    {
        $aspect = new DynamoDbClientAspect(
            $this->config,
            $this->instrumentation,
            $this->createMock(Switcher::class)
        );

        $expectedResult = ['Item' => ['id' => 'test']];
        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willReturn($expectedResult);

        $this->instrumentation->expects($this->never())->method('startSpan');
        $this->meter->expects($this->never())->method('createHistogram');

        $result = $aspect->process($this->proceedingJoinPoint);

        $this->assertEquals($expectedResult, $result);
    }

    public function testProcessWhenServiceIsNotDynamoDB(): void
    {
        $this->apiService->setMetadata(['serviceId' => 'S3']);

        $aspect = new DynamoDbClientAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $expectedResult = ['Buckets' => []];
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
        $command = $this->buildCommand('GetItem', ['TableName' => 'Users']);

        $this->proceedingJoinPoint->arguments = ['keys' => ['command' => $command]];

        $aspect = new DynamoDbClientAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        // Span
        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with(
                $this->equalTo('DynamoDB GetItem'),
                $this->equalTo(SpanKind::KIND_CLIENT),
                $this->equalTo([
                    DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_AWS_DYNAMODB,
                    DbAttributes::DB_OPERATION_NAME => 'GetItem',
                    DbAttributes::DB_COLLECTION_NAME => 'Users',
                    'db.system' => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_AWS_DYNAMODB,
                    'db.operation' => 'GetItem',
                    'db.sql.table' => 'Users',
                    'aws.dynamodb.table_names' => 'Users',
                ])
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
                    DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_AWS_DYNAMODB,
                    DbAttributes::DB_OPERATION_NAME => 'GetItem',
                    DbAttributes::DB_COLLECTION_NAME => 'Users',
                    DbAttributes::DB_QUERY_SUMMARY => 'GetItem Users',
                ])
            );

        $expectedResult = ['Item' => ['id' => ['S' => 'user123']]];
        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willReturn($expectedResult);

        $result = $aspect->process($this->proceedingJoinPoint);

        $this->assertEquals($expectedResult, $result);
    }

    public function testProcessWithException(): void
    {
        $command = $this->buildCommand('PutItem', ['TableName' => 'Users']);

        $this->proceedingJoinPoint->arguments = ['keys' => ['command' => $command]];

        $aspect = new DynamoDbClientAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        // Span
        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with('DynamoDB PutItem')
            ->willReturn($this->spanScope);

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
                    DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_AWS_DYNAMODB,
                    DbAttributes::DB_OPERATION_NAME => 'PutItem',
                    DbAttributes::DB_COLLECTION_NAME => 'Users',
                    DbAttributes::DB_QUERY_SUMMARY => 'PutItem Users',
                ]
            );

        $exception = new Exception('DynamoDB error');

        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willThrowException($exception);

        $this->spanScope->expects($this->once())
            ->method('recordException')
            ->with($exception);

        $this->spanScope->expects($this->once())->method('end');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('DynamoDB error');

        $aspect->process($this->proceedingJoinPoint);
    }

    private function buildCommand(string $name, array $input): object
    {
        return new class($name, $input) {
            public function __construct(private string $name, private array $input)
            {
            }

            public function getName()
            {
                return $this->name;
            }

            public function toArray()
            {
                return $this->input;
            }
        };
    }
}
