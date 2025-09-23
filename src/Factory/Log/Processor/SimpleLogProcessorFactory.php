<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Log\Processor;

use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\LogRecordProcessorInterface;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;

class SimpleLogProcessorFactory implements LogProcessorFactoryInterface
{
    public function make(LogRecordExporterInterface $exporter): LogRecordProcessorInterface
    {
        return new SimpleLogRecordProcessor($exporter);
    }
}
