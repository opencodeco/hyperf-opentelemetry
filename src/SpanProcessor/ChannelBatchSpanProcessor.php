<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\SpanProcessor;

use Hyperf\Coordinator\Timer;
use Hyperf\Coroutine\Coroutine;
use OpenTelemetry\API\Behavior\LogsMessagesTrait;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Trace\ReadableSpanInterface;
use OpenTelemetry\SDK\Trace\ReadWriteSpanInterface;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use Swoole\Coroutine\Channel;
use Throwable;

class ChannelBatchSpanProcessor implements SpanProcessorInterface
{
    use LogsMessagesTrait;

    public const DEFAULT_FLUSH_INTERVAL = 5000;

    public const DEFAULT_CHANNEL_CAPACITY = 128;

    public const DEFAULT_MAX_EXPORT_BATCH_SIZE = 512;

    private ContextInterface $exportContext;

    private bool $closed = false;

    private bool $async;

    private ?Channel $channel = null;

    private ?int $timerId = null;

    /** @var list<SpanDataInterface> */
    private array $batch = [];


    public function __construct(
        private readonly SpanExporterInterface $exporter,
        private readonly int $maxBatchSize = self::DEFAULT_MAX_EXPORT_BATCH_SIZE,
        int $channelCapacity = self::DEFAULT_CHANNEL_CAPACITY,
        private readonly float $flushInterval = self::DEFAULT_FLUSH_INTERVAL,
    ) {
        $this->exportContext = Context::getCurrent();
        $this->async = Coroutine::id() > 0;

        if ($this->async) {
            $this->channel = new Channel($channelCapacity);
            $this->startConsumer();
            $this->startFlushTimer();
        }
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

        if (! $this->async) {
            $this->exportSync([$spanData]);
            return;
        }

        $this->batch[] = $spanData;

        if (count($this->batch) >= $this->maxBatchSize) {
            $this->pushBatch();
        }
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        if ($this->closed) {
            return false;
        }

        if ($this->batch !== []) {
            if ($this->async) {
                $this->pushBatch();
            } else {
                $this->exportSync($this->batch);
                $this->batch = [];
            }
        }

        return $this->exporter->forceFlush($cancellation);
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        if ($this->closed) {
            return false;
        }

        $this->closed = true;

        if ($this->async) {
            if ($this->batch !== []) {
                $this->pushBatch();
            }
            $this->channel?->close();
        } else {
            if ($this->batch !== []) {
                $this->exportSync($this->batch);
                $this->batch = [];
            }
        }

        return $this->exporter->shutdown($cancellation);
    }

    private function pushBatch(): void
    {
        if ($this->batch === [] || $this->channel === null) {
            return;
        }

        $batch = $this->batch;
        $this->batch = [];

        $success = $this->channel->push($batch, 0);

        if ($success === false) {
            self::logWarning(sprintf('[OTel] Channel full, dropped %d span(s)', count($batch)));
        }
    }

    private function startConsumer(): void
    {
        $channel = $this->channel;
        $exporter = $this->exporter;
        $exportContext = $this->exportContext;

        Coroutine::create(static function () use ($channel, $exporter, $exportContext): void {
            while (true) {
                /** @var list<SpanDataInterface>|false $batch */
                $batch = $channel->pop();

                if ($batch === false) {
                    break;
                }

                $scope = $exportContext->activate();
                try {
                    $exporter->export($batch)->await();
                } catch (Throwable $e) {
                    self::logError('Unhandled export error', ['exception' => $e]);
                } finally {
                    $scope->detach();
                }
            }
        });
    }

    private function startFlushTimer(): void
    {
        $timer = new Timer();
        $this->timerId = $timer->tick($this->flushInterval, function () use ($timer): void {
            if ($this->closed) {
                if ($this->timerId !== null) {
                    $timer->clear($this->timerId);
                    $this->timerId = null;
                }
                return;
            }

            if ($this->batch !== []) {
                $this->pushBatch();
            }


        });
    }

    /**
     * @param list<SpanDataInterface> $spans
     */
    private function exportSync(array $spans): void
    {
        $scope = $this->exportContext->activate();
        try {
            $this->exporter->export($spans)->await();
        } catch (Throwable $e) {
            self::logError('Unhandled export error', ['exception' => $e]);
        } finally {
            $scope->detach();
        }
    }
}
