<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Factory\Log;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\LogRecordProcessorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use Hyperf\OpenTelemetry\Factory\Log\Exporter\LogExporterFactoryInterface;
use Hyperf\OpenTelemetry\Factory\Log\Processor\LogProcessorFactoryInterface;

class LoggerProviderFactory
{
    public function __construct(
        protected readonly ConfigInterface $config,
        protected readonly ContainerInterface $container,
        protected readonly ResourceInfo $resource,
    ) {
    }

    public function getLoggerProvider(): LoggerProviderInterface
    {
        $exporter = $this->getExporter();
        $processor = $this->getProcessor($exporter);

        return LoggerProvider::builder()
            ->setResource($this->resource)
            ->addLogRecordProcessor($processor)
            ->build();
    }

    public function getExporter(): LogRecordExporterInterface
    {
        $name = $this->config->get('open-telemetry.logs.exporter', 'stdout');

        /**
         * @var LogExporterFactoryInterface $driver
         */
        $driver = $this->container->get(
            $this->config->get("open-telemetry.logs.exporters.{$name}.driver")
        );

        return $driver->make();
    }

    public function getProcessor(LogRecordExporterInterface $exporter): LogRecordProcessorInterface
    {
        $name = $this->config->get('open-telemetry.logs.processor', 'simple');

        /**
         * @var LogProcessorFactoryInterface $driver
         */
        $driver = $this->container->get(
            $this->config->get("open-telemetry.logs.processors.{$name}.driver")
        );

        return $driver->make($exporter);
    }
}
