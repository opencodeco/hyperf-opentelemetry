<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Exception;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SemConv\Attributes\CodeAttributes;
use OpenTelemetry\SemConv\Attributes\ExceptionAttributes;
use OpenTelemetry\SemConv\Attributes\HttpAttributes;
use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Support\SpanScope;

/**
 * @internal
 */
class SpanScopeTest extends TestCase
{
    public function testBuildSpanScope(): void
    {
        $spanMock = $this->createMock(SpanInterface::class);
        $scopeMock = $this->createMock(ScopeInterface::class);
        $contextMock = $this->createMock(ContextInterface::class);

        $spanScope = new SpanScope($spanMock, $scopeMock, $contextMock);

        $this->assertEquals($contextMock, $spanScope->getContext());
    }

    public function testRecordExceptionSetsAttributesAndStatus()
    {
        $spanMock = $this->createMock(SpanInterface::class);
        $scopeMock = $this->createMock(ScopeInterface::class);
        $contextMock = $this->createMock(ContextInterface::class);

        $exception = new Exception('Error', 0);

        $spanMock->expects($this->once())
            ->method('setAttributes')
            ->with([
                ExceptionAttributes::EXCEPTION_TYPE => Exception::class,
                ExceptionAttributes::EXCEPTION_MESSAGE => $exception->getMessage(),
                ExceptionAttributes::EXCEPTION_STACKTRACE => $exception->getTraceAsString(),
                CodeAttributes::CODE_LINE_NUMBER => $exception->getLine(),
                CodeAttributes::CODE_FUNCTION_NAME => 'Tests\Unit\Support\SpanScopeTest::testRecordExceptionSetsAttributesAndStatus',
            ]);

        $spanMock->expects($this->once())
            ->method('setStatus')
            ->with(StatusCode::STATUS_ERROR, $exception->getMessage());

        $spanMock->expects($this->once())
            ->method('recordException')
            ->with($exception);

        $spanScope = new SpanScope($spanMock, $scopeMock, $contextMock);
        $spanScope->recordException($exception);
    }

    public function testRecordExceptionWithNullDoesNothing()
    {
        $spanMock = $this->createMock(SpanInterface::class);
        $scopeMock = $this->createMock(ScopeInterface::class);
        $contextMock = $this->createMock(ContextInterface::class);

        $spanMock->expects($this->never())->method('setAttributes');
        $spanMock->expects($this->never())->method('setStatus');
        $spanMock->expects($this->never())->method('recordException');

        $spanScope = new SpanScope($spanMock, $scopeMock, $contextMock);
        $spanScope->recordException(null);
    }

    public function testEndCallsSpanEndAndScopeDetach()
    {
        $spanMock = $this->createMock(SpanInterface::class);
        $scopeMock = $this->createMock(ScopeInterface::class);
        $contextMock = $this->createMock(ContextInterface::class);

        $spanMock->expects($this->once())->method('end');
        $scopeMock->expects($this->once())->method('detach');

        $spanScope = new SpanScope($spanMock, $scopeMock, $contextMock);
        $spanScope->end();
    }

    public function testSetAttributes(): void
    {
        $spanMock = $this->createMock(SpanInterface::class);
        $scopeMock = $this->createMock(ScopeInterface::class);
        $contextMock = $this->createMock(ContextInterface::class);

        $spanScope = new SpanScope($spanMock, $scopeMock, $contextMock);

        $attributes = [HttpAttributes::HTTP_RESPONSE_STATUS_CODE => '200'];

        $spanMock->expects($this->once())->method('setAttributes')->with($attributes)->willReturnSelf();

        $spanScope->setAttributes($attributes);
    }

    public function testSetStatusSuccess(): void
    {
        $spanMock = $this->createMock(SpanInterface::class);
        $scopeMock = $this->createMock(ScopeInterface::class);
        $contextMock = $this->createMock(ContextInterface::class);

        $spanScope = new SpanScope($spanMock, $scopeMock, $contextMock);

        $spanMock->expects($this->once())
            ->method('setStatus')
            ->with(StatusCode::STATUS_OK)
            ->willReturnSelf();

        $spanScope->setStatus(StatusCode::STATUS_OK);
    }

    public function testSetStatusError(): void
    {
        $spanMock = $this->createMock(SpanInterface::class);
        $scopeMock = $this->createMock(ScopeInterface::class);
        $contextMock = $this->createMock(ContextInterface::class);

        $spanScope = new SpanScope($spanMock, $scopeMock, $contextMock);

        $spanMock->expects($this->once())
            ->method('setStatus')
            ->with(StatusCode::STATUS_ERROR, 'Server Error')
            ->willReturnSelf();

        $spanScope->setStatus(StatusCode::STATUS_ERROR, 'Server Error');
    }

    public function testSetAttribute(): void
    {
        $spanMock = $this->createMock(SpanInterface::class);
        $scopeMock = $this->createMock(ScopeInterface::class);
        $contextMock = $this->createMock(ContextInterface::class);

        $spanScope = new SpanScope($spanMock, $scopeMock, $contextMock);

        $key = 'messaging.message.id';
        $value = uniqid();

        $spanMock->expects($this->once())
            ->method('setAttribute')
            ->with($key, $value)
            ->willReturnSelf();

        $spanScope->setAttribute($key, $value);
    }

    public function testDetach()
    {
        $spanMock = $this->createMock(SpanInterface::class);
        $scopeMock = $this->createMock(ScopeInterface::class);
        $contextMock = $this->createMock(ContextInterface::class);

        $spanScope = new SpanScope($spanMock, $scopeMock, $contextMock);

        $scopeMock->expects($this->once())->method('detach');

        $spanScope->detach();
    }
}
