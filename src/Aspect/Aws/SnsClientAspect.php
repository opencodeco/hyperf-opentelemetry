<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Aspect\Aws;

use Hyperf\Di\Aop\ProceedingJoinPoint;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\MessagingIncubatingAttributes as Msg;
use Hyperf\OpenTelemetry\Aspect\AbstractAspect;
use Throwable;

class SnsClientAspect extends AbstractAspect
{
    public array $classes = [
        'Aws\AwsClientTrait::execute',
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $instance = $proceedingJoinPoint->getInstance();
        $service = $instance?->getApi()->getMetadata()['serviceId'] ?? '';

        if (! $this->isTelemetryEnabled() || stripos($service, 'sns') === false) {
            return $proceedingJoinPoint->process();
        }

        $command = $proceedingJoinPoint->arguments['keys']['command'] ?? null;
        $operation = $command?->getName() ?? '';
        $input = $command?->toArray() ?? [];

        $topicArn = $input['TopicArn'] ?? 'unknown';
        $topicName = $topicArn !== '' ? basename($topicArn) : 'unknown';

        $scope = null;
        $start = microtime(true);
        $metricErrors = [];

        if ($this->isTracingEnabled) {
            $scope = $this->instrumentation->startSpan(
                name: 'SNS ' . ucfirst($operation),
                spanKind: SpanKind::KIND_PRODUCER,
                attributes: [
                    Msg::MESSAGING_SYSTEM => 'aws_sns',
                    Msg::MESSAGING_DESTINATION_NAME => $topicName,
                    Msg::MESSAGING_OPERATION_NAME => $operation,
                    Msg::MESSAGING_OPERATION_TYPE => 'send',
                    'aws.sns.topic.arn' => $topicArn,
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
                    ->createHistogram('messaging.client.sent.messages', 'ms')
                    ->record(
                        $duration,
                        array_merge(
                            $metricErrors,
                            [
                                Msg::MESSAGING_SYSTEM => 'aws_sns',
                                Msg::MESSAGING_OPERATION_NAME => $operation,
                                Msg::MESSAGING_DESTINATION_NAME => $topicName,
                                Msg::MESSAGING_OPERATION_TYPE => Msg::MESSAGING_OPERATION_TYPE_VALUE_SEND,
                            ]
                        )
                    );
            }
        }
    }

    protected function featureName(): string
    {
        return 'aws_sns';
    }
}
