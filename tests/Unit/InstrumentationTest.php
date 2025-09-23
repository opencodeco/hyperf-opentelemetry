<?php

declare(strict_types=1);

namespace Tests\Unit;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Metrics\Noop\NoopMeter;
use OpenTelemetry\API\Trace\NoopTracer;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\NoopTextMapPropagator;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SemConv\Attributes\HttpAttributes;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Instrumentation;

/**
 * @internal
 */
class InstrumentationTest extends TestCase
{
    private TracerInterface $tracer;

    private object $tracerProvider;

    protected function setUp(): void
    {
        parent::setUp();
        Configurator::createNoop()->activate();

        $this->tracer = $this->createMock(TracerInterface::class);

        $this->tracerProvider = new class($this->tracer) implements TracerProviderInterface {
            public function __construct(private TracerInterface $tracer)
            {
            }

            public function getTracer(
                string $name,
                ?string $version = null,
                ?string $schemaUrl = null,
                iterable $attributes = []
            ): TracerInterface {
                return $this->tracer;
            }
        };
    }

    public function testGetMeter(): void
    {
        $instrumentation = new Instrumentation(new CachedInstrumentation('test'));
        $this->assertInstanceOf(NoopMeter::class, $instrumentation->meter());
    }

    public function testGetTracer(): void
    {
        $instrumentation = new Instrumentation(new CachedInstrumentation('test'));
        $this->assertInstanceOf(NoopTracer::class, $instrumentation->tracer());
    }

    public function testGetPropagator(): void
    {
        $instrumentation = new Instrumentation(new CachedInstrumentation('test'));
        $this->assertInstanceOf(NoopTextMapPropagator::class, $instrumentation->propagator());
    }

    public function testStartSpan(): void
    {
        Configurator::create()->withTracerProvider($this->tracerProvider)->activate();

        $instrumentation = new Instrumentation(new CachedInstrumentation('test'));

        $spanBuilderMock = $this->createMock(SpanBuilderInterface::class);
        $spanMock = $this->createMock(SpanInterface::class);
        $contextMock = $this->createMock(ContextInterface::class);
        $scopeMock = $this->createMock(ScopeInterface::class);

        $this->tracer->method('spanBuilder')->with('test')->willReturn($spanBuilderMock);

        $spanBuilderMock->method('setSpanKind')
            ->with(SpanKind::KIND_SERVER)
            ->willReturnSelf();

        $parent = $this->createMock(ContextInterface::class);
        $spanBuilderMock->expects($this->once())->method('setParent')->with($parent)->willReturnSelf();

        $spanBuilderMock->expects($this->once())->method('setStartTimestamp')->with(0)->willReturnSelf();

        $attributes = [HttpAttributes::HTTP_REQUEST_METHOD => 'POST'];
        $spanBuilderMock->expects($this->once())->method('setAttributes')->with($attributes)->willReturnSelf();

        $spanBuilderMock->expects($this->once())->method('startSpan')->willReturn($spanMock);

        $spanMock->expects($this->once())->method('storeInContext')->with($parent)->willReturn($contextMock);
        $contextMock->expects($this->once())->method('activate')->willReturn($scopeMock);

        $instrumentation->startSpan(
            name: 'test',
            spanKind: SpanKind::KIND_SERVER,
            attributes: $attributes,
            explicitContext: $parent
        );
    }
}
