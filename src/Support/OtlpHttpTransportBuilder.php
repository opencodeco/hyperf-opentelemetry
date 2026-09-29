<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Support;

use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory;
use OpenTelemetry\SDK\Common\Export\TransportInterface;

class OtlpHttpTransportBuilder
{
    public function __construct(
        private readonly SwooleCoroutineDetector $coroutines = new SwooleCoroutineDetector(),
    ) {
    }

    public function create(
        string $endpoint,
        string $contentType,
        array $headers = [],
        $compression = null,
        float $timeout = 10.0,
        int $retryDelay = 100,
        int $maxRetries = 3,
    ): TransportInterface {
        if (! $this->coroutines->extensionLoaded()) {
            return (new OtlpHttpTransportFactory())->create(
                endpoint: $endpoint,
                contentType: $contentType,
                headers: $headers,
                compression: $compression,
                timeout: $timeout,
                retryDelay: $retryDelay,
                maxRetries: $maxRetries,
            );
        }

        if ($compression === 'none') {
            $compression = null;
        }

        return (new PsrTransportFactory(new CoroutineHttpClient($this->coroutines, $timeout)))->create(
            $endpoint,
            $contentType,
            $headers,
            $compression,
            $timeout,
            $retryDelay,
            $maxRetries,
        );
    }
}
