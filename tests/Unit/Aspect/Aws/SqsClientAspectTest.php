<?php

declare(strict_types=1);

namespace Tests\Unit\Aspect\Aws;

use Exception;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\OpenTelemetry\Aspect\Aws\SqsClientAspect;
use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Support\SpanScope;
use Hyperf\OpenTelemetry\Switcher;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\MessagingIncubatingAttributes as MsgAttributes;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class SqsClientAspectTest extends TestCase
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
            public array $metadata = ['serviceId' => 'SQS'];

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
        $aspect = new SqsClientAspect(
            $this->config,
            $this->instrumentation,
            $this->createMock(Switcher::class)
        );

        $expectedResult = ['Messages' => []];
        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willReturn($expectedResult);

        $this->instrumentation->expects($this->never())->method('startSpan');
        $this->meter->expects($this->never())->method('createHistogram');

        $result = $aspect->process($this->proceedingJoinPoint);

        $this->assertEquals($expectedResult, $result);
    }

    public function testProcessWhenServiceIsNotSqs(): void
    {
        $this->apiService->setMetadata(['serviceId' => 'DynamoDB']);

        $this->switcher->method('isTracingEnabled')->willReturn(true);
        $aspect = new SqsClientAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $expectedResult = ['Item' => []];
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
        $command = $this->buildCommand(
            'SendMessage',
            ['QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/notifications']
        );

        $this->proceedingJoinPoint->arguments = ['keys' => ['command' => $command]];

        $aspect = new SqsClientAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        // Span
        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with(
                $this->equalTo('SQS send notifications'),
                $this->equalTo(SpanKind::KIND_PRODUCER),
                $this->equalTo([
                    MsgAttributes::MESSAGING_SYSTEM => MsgAttributes::MESSAGING_SYSTEM_VALUE_AWS_SQS,
                    MsgAttributes::MESSAGING_DESTINATION_NAME => 'notifications',
                    MsgAttributes::MESSAGING_OPERATION_TYPE => MsgAttributes::MESSAGING_OPERATION_TYPE_VALUE_SEND,
                    MsgAttributes::MESSAGING_OPERATION_NAME => 'SendMessage',
                    'aws.sqs.queue.url' => 'https://sqs.us-east-1.amazonaws.com/123456789012/notifications',
                ])
            )
            ->willReturn($this->spanScope);

        $this->spanScope->expects($this->once())
            ->method('setAttribute')
            ->with(MsgAttributes::MESSAGING_MESSAGE_ID, 'msg-123');

        $this->spanScope->expects($this->once())
            ->method('setStatus')
            ->with(StatusCode::STATUS_OK);

        $this->spanScope->expects($this->once())->method('end');

        // Metric
        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->with('messaging.client.sent.messages', 'ms')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                $this->isType('float'),
                $this->equalTo([
                    MsgAttributes::MESSAGING_SYSTEM => MsgAttributes::MESSAGING_SYSTEM_VALUE_AWS_SQS,
                    MsgAttributes::MESSAGING_OPERATION_NAME => 'SendMessage',
                    MsgAttributes::MESSAGING_DESTINATION_NAME => 'notifications',
                    MsgAttributes::MESSAGING_OPERATION_TYPE => MsgAttributes::MESSAGING_OPERATION_TYPE_VALUE_SEND,
                ])
            );

        $expectedResult = ['MessageId' => 'msg-123'];
        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willReturn($expectedResult);

        $result = $aspect->process($this->proceedingJoinPoint);

        $this->assertEquals($expectedResult, $result);
    }

    public function testProcessWithReceiveMessageOperation(): void
    {
        $command = $this->buildCommand(
            'ReceiveMessage',
            ['QueueUrl' => 'https://sqs.us-west-2.amazonaws.com/123456789012/orders-queue']
        );

        $this->proceedingJoinPoint->arguments = ['keys' => ['command' => $command]];

        $this->switcher->method('isTracingEnabled')->willReturn(true);
        $aspect = new SqsClientAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        // Span
        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with(
                $this->equalTo('SQS receive orders-queue'),
                $this->equalTo(SpanKind::KIND_CLIENT),
                $this->equalTo([
                    MsgAttributes::MESSAGING_SYSTEM => MsgAttributes::MESSAGING_SYSTEM_VALUE_AWS_SQS,
                    MsgAttributes::MESSAGING_DESTINATION_NAME => 'orders-queue',
                    MsgAttributes::MESSAGING_OPERATION_TYPE => MsgAttributes::MESSAGING_OPERATION_TYPE_VALUE_RECEIVE,
                    MsgAttributes::MESSAGING_OPERATION_NAME => 'ReceiveMessage',
                    'aws.sqs.queue.url' => 'https://sqs.us-west-2.amazonaws.com/123456789012/orders-queue',
                ])
            )
            ->willReturn($this->spanScope);

        $this->spanScope->expects($this->never())
            ->method('setAttribute');

        $this->spanScope->expects($this->once())
            ->method('setStatus')
            ->with(StatusCode::STATUS_OK);

        $this->spanScope->expects($this->once())->method('end');

        // Metric
        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->with('messaging.client.consumed.messages', 'ms')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                $this->isType('float'),
                $this->equalTo([
                    MsgAttributes::MESSAGING_SYSTEM => MsgAttributes::MESSAGING_SYSTEM_VALUE_AWS_SQS,
                    MsgAttributes::MESSAGING_OPERATION_NAME => 'ReceiveMessage',
                    MsgAttributes::MESSAGING_DESTINATION_NAME => 'orders-queue',
                    MsgAttributes::MESSAGING_OPERATION_TYPE => MsgAttributes::MESSAGING_OPERATION_TYPE_VALUE_RECEIVE,
                ])
            );

        $expectedResult = ['Messages' => [['MessageId' => 'msg-456']]];
        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willReturn($expectedResult);

        $result = $aspect->process($this->proceedingJoinPoint);

        $this->assertEquals($expectedResult, $result);
    }

    public function testProcessWithException(): void
    {
        $command = $this->buildCommand(
            'ReceiveMessage',
            ['QueueUrl' => 'https://sqs.us-east-1.amazonaws.com/123456789012/events']
        );

        $this->proceedingJoinPoint->arguments = ['keys' => ['command' => $command]];

        $aspect = new SqsClientAspect(
            $this->config,
            $this->instrumentation,
            $this->switcher
        );

        $exception = new Exception('SQS error');

        // Span
        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->willReturn($this->spanScope);

        $this->spanScope->expects($this->once())
            ->method('recordException')
            ->with($exception);

        $this->spanScope->expects($this->once())->method('end');

        // Metric
        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->with('messaging.client.consumed.messages', 'ms')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                $this->isType('float'),
                $this->equalTo([
                    ErrorAttributes::ERROR_TYPE => Exception::class,
                    MsgAttributes::MESSAGING_SYSTEM => MsgAttributes::MESSAGING_SYSTEM_VALUE_AWS_SQS,
                    MsgAttributes::MESSAGING_OPERATION_NAME => 'ReceiveMessage',
                    MsgAttributes::MESSAGING_DESTINATION_NAME => 'events',
                    MsgAttributes::MESSAGING_OPERATION_TYPE => MsgAttributes::MESSAGING_OPERATION_TYPE_VALUE_RECEIVE,
                ])
            );

        $this->proceedingJoinPoint->expects($this->once())
            ->method('process')
            ->willThrowException($exception);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('SQS error');

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
