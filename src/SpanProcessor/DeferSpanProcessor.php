<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\SpanProcessor;

use Hyperf\Context\Context;
use OpenTelemetry\API\Behavior\LogsMessagesTrait;
use OpenTelemetry\Context\Context as OtelContext;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Trace\ReadableSpanInterface;
use OpenTelemetry\SDK\Trace\ReadWriteSpanInterface;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function Hyperf\Coroutine\defer;

class DeferSpanProcessor implements SpanProcessorInterface
{
    use LogsMessagesTrait;

    private const CONTEXT_KEY = 'otel.defer.spans';

    private ContextInterface $exportContext;

    private bool $closed = false;

    public function __construct(
        private readonly SpanExporterInterface $exporter,
    ) {
        $this->exportContext = OtelContext::getCurrent();
    }

    public function onStart(ReadWriteSpanInterface $span, ContextInterface $parentContext): void
    {
    }

    public function onEnd(ReadableSpanInterface $span): void
    {
        if ($this->closed) {
            return;
        }

        if (! $span->getContext()->isSampled()) {
            return;
        }

        $spanData = $span->toSpanData();

        if ($this->isHttpRequestContext()) {
            $this->bufferAndDefer($spanData);
            return;
        }

        $this->exportImmediate($spanData);
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        if ($this->closed) {
            return false;
        }

        return $this->exporter->forceFlush($cancellation);
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        if ($this->closed) {
            return false;
        }

        $this->closed = true;

        return $this->exporter->shutdown($cancellation);
    }

    private function isHttpRequestContext(): bool
    {
        return Context::has(ServerRequestInterface::class);
    }

    private function bufferAndDefer(SpanDataInterface $spanData): void
    {
        /** @var SpanDataInterface[] $spans */
        $spans = Context::get(self::CONTEXT_KEY, []);
        $isFirst = $spans === [];

        $spans[] = $spanData;
        Context::set(self::CONTEXT_KEY, $spans);

        if ($isFirst) {
            $exporter = $this->exporter;
            $exportContext = $this->exportContext;

            defer(static function () use ($exporter, $exportContext): void {
                /** @var SpanDataInterface[] $pendingSpans */
                $pendingSpans = Context::get(self::CONTEXT_KEY, []);
                Context::set(self::CONTEXT_KEY, []);

                if ($pendingSpans === []) {
                    return;
                }

                $scope = $exportContext->activate();
                try {
                    $exporter->export($pendingSpans)->await();
                } catch (Throwable $e) {
                    self::logError('Unhandled export error', ['exception' => $e]);
                } finally {
                    $scope->detach();
                }
            });
        }
    }

    private function exportImmediate(SpanDataInterface $spanData): void
    {
        $scope = $this->exportContext->activate();
        try {
            $this->exporter->export([$spanData])->await();
        } catch (Throwable $e) {
            self::logError('Unhandled export error', ['exception' => $e]);
        } finally {
            $scope->detach();
        }
    }
}
