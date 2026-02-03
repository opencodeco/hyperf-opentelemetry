<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Aspect\Aws;

use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\OpenTelemetry\Aspect\AbstractAspect;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\MessagingIncubatingAttributes as Msg;
use Throwable;

class SqsClientAspect extends AbstractAspect
{
    public array $classes = [
        'Aws\AwsClientTrait::execute',
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $instance = $proceedingJoinPoint->getInstance();
        $service = $instance?->getApi()->getMetadata()['serviceId'] ?? '';

        if (! $this->isTelemetryEnabled() || stripos($service, 'sqs') === false) {
            return $proceedingJoinPoint->process();
        }

        $command = $proceedingJoinPoint->arguments['keys']['command'] ?? null;
        $operation = $command?->getName() ?? '';
        $input = $command?->toArray() ?? [];

        $operationType = $this->resolveOperationType($operation);

        $queueName = $input['QueueName'] ?? $this->extractQueueName($input['QueueUrl'] ?? 'unknown');
        $queueUrl = $input['QueueUrl'] ?? 'unknown';

        $spanKind = ($operationType == 'send') ? SpanKind::KIND_PRODUCER : SpanKind::KIND_CLIENT;

        $scope = null;
        $start = microtime(true);
        $metricErrors = [];

        if ($this->isTracingEnabled) {
            $scope = $this->instrumentation->startSpan(
                name: "SQS {$operationType} {$queueName}",
                spanKind: $spanKind,
                attributes: [
                    Msg::MESSAGING_SYSTEM => Msg::MESSAGING_SYSTEM_VALUE_AWS_SQS,
                    Msg::MESSAGING_DESTINATION_NAME => $queueName,
                    Msg::MESSAGING_OPERATION_TYPE => $operationType,
                    Msg::MESSAGING_OPERATION_NAME => $operation,
                    'aws.sqs.queue.url' => $queueUrl,
                ]
            );
        }

        try {
            $result = $proceedingJoinPoint->process();

            if ($scope) {
                $messageId = $result['MessageId'] ?? null;

                if ($messageId) {
                    $scope->setAttribute(Msg::MESSAGING_MESSAGE_ID, $messageId);
                }

                $scope->setStatus(StatusCode::STATUS_OK);
            }

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
                    ->createHistogram($this->metricName($operationType), 'ms')
                    ->record(
                        $duration,
                        array_merge(
                            $metricErrors,
                            [
                                Msg::MESSAGING_SYSTEM => Msg::MESSAGING_SYSTEM_VALUE_AWS_SQS,
                                Msg::MESSAGING_OPERATION_NAME => $operation,
                                Msg::MESSAGING_DESTINATION_NAME => $queueName,
                                Msg::MESSAGING_OPERATION_TYPE => $operationType,
                            ]
                        )
                    );
            }
        }
    }

    protected function featureName(): string
    {
        return 'aws_sqs';
    }

    private function resolveOperationType(string $operation): string
    {
        return match (strtolower($operation)) {
            'sendmessage', 'sendmessagebatch' => Msg::MESSAGING_OPERATION_TYPE_VALUE_SEND,
            'receivemessage' => Msg::MESSAGING_OPERATION_TYPE_VALUE_RECEIVE,
            'createqueue' => Msg::MESSAGING_OPERATION_TYPE_VALUE_CREATE,
            'deletemessage', 'deletemessagebatch', 'purgequeue' => Msg::MESSAGING_OPERATION_TYPE_VALUE_SETTLE,
            'getqueueurl' => 'describe',
            default => 'unknown',
        };
    }

    private function metricName(string $operationType): string
    {
        return match ($operationType) {
            Msg::MESSAGING_OPERATION_TYPE_VALUE_SEND => 'messaging.client.sent.messages',
            default => 'messaging.client.consumed.messages',
        };
    }

    private function extractQueueName(string $queueUrl): string
    {
        return basename(parse_url($queueUrl, PHP_URL_PATH) ?? '') ?: $queueUrl;
    }
}
