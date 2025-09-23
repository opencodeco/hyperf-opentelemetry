<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Log\Exporter;

use OpenTelemetry\SDK\Logs\Exporter\ConsoleExporterFactory;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;

class StdoutLogExporterFactory implements LogExporterFactoryInterface
{
    public function make(): LogRecordExporterInterface
    {
        return (new ConsoleExporterFactory())->create();
    }
}
