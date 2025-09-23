<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Log\Exporter;

use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;

interface LogExporterFactoryInterface
{
    public function make(): LogRecordExporterInterface;
}
