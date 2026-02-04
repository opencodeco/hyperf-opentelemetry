<?php

declare(strict_types=1);

namespace Tests\Unit\Listener;

use Exception;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\Connection;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Database\Events\StatementPrepared;
use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Listener\DbQueryExecutedListener;
use Hyperf\OpenTelemetry\Support\SpanScope;
use Hyperf\OpenTelemetry\Switcher;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\Attributes\DbAttributes;
use OpenTelemetry\SemConv\Attributes\ErrorAttributes;
use OpenTelemetry\SemConv\Attributes\ServerAttributes;
use OpenTelemetry\SemConv\Metrics\DbMetrics;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DbQueryExecutedListenerTest extends TestCase
{
    private ConfigInterface $config;

    private Instrumentation $instrumentation;

    private Switcher $switcher;

    private Connection $connection;

    private MeterInterface $meter;

    private HistogramInterface $histogram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(ConfigInterface::class);
        $this->instrumentation = $this->createMock(Instrumentation::class);
        $this->switcher = $this->createMock(Switcher::class);
        $this->connection = $this->createMock(Connection::class);
        $this->meter = $this->createMock(MeterInterface::class);
        $this->histogram = $this->createMock(HistogramInterface::class);

        $this->switcher->method('isTracingEnabled')->willReturn(true);
        $this->switcher->method('isMetricsEnabled')->willReturn(true);

        $this->instrumentation->method('meter')->willReturn($this->meter);

        $this->connection->method('getName')->willReturn('test');
        $this->connection->expects($this->any())->method('getDatabaseName')->willReturn('testdb');

        $this->connection->expects($this->any())
            ->method('getConfig')
            ->willReturnMap([
                ['host', 'localhost'],
                ['port', '8000'],
            ]);

        $this->config->method('get')->willReturn(false);
    }

    public function testListenReturnsCorrectEvents()
    {
        $listener = new DbQueryExecutedListener($this->config, $this->instrumentation, $this->switcher);

        $this->assertEquals([QueryExecuted::class], $listener->listen());
    }

    public function testProcessWithWrongEventShouldDoNothing(): void
    {
        $listener = new DbQueryExecutedListener($this->config, $this->instrumentation, $this->switcher);

        $this->instrumentation->expects($this->never())->method('startSpan');
        $this->meter->expects($this->never())->method('createHistogram');

        $listener->process($this->createMock(StatementPrepared::class));
    }

    public function testProcessWhenTracingIsDisabledShouldDoNothing(): void
    {
        $listener = new DbQueryExecutedListener(
            $this->config,
            $this->instrumentation,
            $this->createMock(Switcher::class)
        );

        $event = new QueryExecuted(
            'select id from table',
            [],
            10.0,
            $this->connection
        );

        $this->instrumentation->expects($this->never())->method('startSpan');
        $this->meter->expects($this->never())->method('createHistogram');

        $listener->process($event);
    }

    public function testProcess(): void
    {
        $this->connection->expects($this->any())->method('getDriverName')->willReturn('pgsql');

        $event = new QueryExecuted(
            $sql = 'SELECT id, name FROM users WHERE active = ?',
            [1],
            25.3,
            $this->connection
        );

        // Span
        $spanScope = $this->createMock(SpanScope::class);

        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with(
                $this->equalTo('postgresql SELECT users'),
                $this->equalTo(SpanKind::KIND_CLIENT),
                [
                    DbAttributes::DB_SYSTEM_NAME => DbAttributes::DB_SYSTEM_NAME_VALUE_POSTGRESQL,
                    DbAttributes::DB_COLLECTION_NAME => 'users',
                    DbAttributes::DB_NAMESPACE => 'testdb',
                    DbAttributes::DB_OPERATION_NAME => 'SELECT',
                    DbAttributes::DB_QUERY_TEXT => $sql,
                    ServerAttributes::SERVER_ADDRESS => 'localhost',
                    ServerAttributes::SERVER_PORT => 8000,
                    'db.system' => DbAttributes::DB_SYSTEM_NAME_VALUE_POSTGRESQL,
                    'db.operation' => 'SELECT',
                    'db.sql.table' => 'users',
                ],
                $this->isType('int')
            )
            ->willReturn($spanScope);

        $spanScope->expects($this->once())->method('end');

        // Metric
        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->with(DbMetrics::DB_CLIENT_OPERATION_DURATION, 'ms')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(25.3, [
                DbAttributes::DB_SYSTEM_NAME => DbAttributes::DB_SYSTEM_NAME_VALUE_POSTGRESQL,
                DbAttributes::DB_COLLECTION_NAME => 'users',
                DbAttributes::DB_NAMESPACE => 'testdb',
                DbAttributes::DB_OPERATION_NAME => 'SELECT',
                DbAttributes::DB_QUERY_SUMMARY => 'SELECT users',
            ]);

        $listener = new DbQueryExecutedListener($this->config, $this->instrumentation, $this->switcher);

        $listener->process($event);
    }

    public function testProcessWithBindingEnabled(): void
    {
        $this->connection->expects($this->any())->method('getDriverName')->willReturn('mysql');

        $event = new QueryExecuted(
            'SELECT id, name FROM users WHERE active = ?',
            [1],
            25.3,
            $this->connection
        );

        $config = $this->createMock(ConfigInterface::class);
        $config->expects($this->once())
            ->method('get')
            ->with('open-telemetry.instrumentation.features.db_query.options.combine_sql_and_bindings', false)
            ->willReturn(true);

        // Span
        $spanScope = $this->createMock(SpanScope::class);

        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with(
                $this->equalTo('mysql SELECT users'),
                $this->equalTo(SpanKind::KIND_CLIENT),
                [
                    DbAttributes::DB_SYSTEM_NAME => DbAttributes::DB_SYSTEM_NAME_VALUE_MYSQL,
                    DbAttributes::DB_COLLECTION_NAME => 'users',
                    DbAttributes::DB_NAMESPACE => 'testdb',
                    DbAttributes::DB_OPERATION_NAME => 'SELECT',
                    DbAttributes::DB_QUERY_TEXT => 'SELECT id, name FROM users WHERE active = \'1\'',
                    ServerAttributes::SERVER_ADDRESS => 'localhost',
                    ServerAttributes::SERVER_PORT => 8000,
                    'db.system' => DbAttributes::DB_SYSTEM_NAME_VALUE_MYSQL,
                    'db.operation' => 'SELECT',
                    'db.sql.table' => 'users',
                ],
                $this->isType('int')
            )
            ->willReturn($spanScope);

        $spanScope->expects($this->once())->method('end');

        // Metric
        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->with(DbMetrics::DB_CLIENT_OPERATION_DURATION, 'ms')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(25.3, [
                DbAttributes::DB_SYSTEM_NAME => DbAttributes::DB_SYSTEM_NAME_VALUE_MYSQL,
                DbAttributes::DB_COLLECTION_NAME => 'users',
                DbAttributes::DB_NAMESPACE => 'testdb',
                DbAttributes::DB_OPERATION_NAME => 'SELECT',
                DbAttributes::DB_QUERY_SUMMARY => 'SELECT users',
            ]);

        $listener = new DbQueryExecutedListener($config, $this->instrumentation, $this->switcher);

        $listener->process($event);
    }

    public function testProcessWithException(): void
    {
        $this->connection->expects($this->any())->method('getDriverName')->willReturn('sqlsrv');

        $exception = new Exception();

        $event = new QueryExecuted(
            'SELECT id, name FROM users WHERE active = ?',
            [1],
            10.5,
            $this->connection,
            $exception
        );

        // Span
        $spanScope = $this->createMock(SpanScope::class);

        $this->instrumentation
            ->expects($this->once())
            ->method('startSpan')
            ->with(
                $this->equalTo('microsoft.sql_server SELECT users')
            )
            ->willReturn($spanScope);

        $spanScope->expects($this->once())->method('end');
        $spanScope->expects($this->once())->method('recordException')->with($exception);

        // Metric
        $this->meter->expects($this->once())
            ->method('createHistogram')
            ->with(DbMetrics::DB_CLIENT_OPERATION_DURATION, 'ms')
            ->willReturn($this->histogram);

        $this->histogram->expects($this->once())
            ->method('record')
            ->with(
                10.5,
                $this->callback(function ($attributes) {
                    $this->assertArrayHasKey(ErrorAttributes::ERROR_TYPE, $attributes);

                    $this->assertEquals($attributes[ErrorAttributes::ERROR_TYPE], Exception::class);
                    return true;
                })
            );

        $listener = new DbQueryExecutedListener($this->config, $this->instrumentation, $this->switcher);

        $listener->process($event);
    }
}
