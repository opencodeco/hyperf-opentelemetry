# hyperf-opentelemetry

[![Status](https://img.shields.io/badge/status-beta-yellow)]() [![License](https://img.shields.io/badge/license-MIT-blue.svg)]() [![PHP](https://img.shields.io/badge/php-%3E%3D8.1-777bb4.svg?logo=php&logoColor=white)]() [![Hyperf](https://img.shields.io/badge/framework-Hyperf-green)]() [![OpenTelemetry](https://img.shields.io/badge/observability-OpenTelemetry-orange)]()

Instrumentation library for Hyperf applications with OpenTelemetry support.

This library enables instrumentation of Hyperf-based applications for exporting metrics, traces, and logs compatible with the OpenTelemetry standard.

---

## ✨ Features

- 📦 Ready-to-use with Swoole and Coroutine
- 📊 Custom metrics support via Meter
- 📈 Trace instrumentation for:
  - HTTP requests (Hyperf\HttpServer)
  - Redis
  - Guzzle
  - SQL queries (Hyperf\Database)
  - MongoDB (GoTask)
- ♻️ Integration with Swoole ContextStorage

---

## 📦 Installation

```shell
composer require opencodeco/hyperf-opentelemetry
```

---

## ⚙️ Configuration
1. Publish the configuration file
```shell
php bin/hyperf.php vendor:publish opencodeco/hyperf-opentelemetry
```

Edit the file config/autoload/open-telemetry.php to adjust settings (enable/disable features, OTLP endpoints, resource attributes, etc).

2. Configure environment variables

Example .env:

```shell
OTEL_TRACES_ENDPOINT=http://otelcol:4318/v1/traces
OTEL_METRICS_ENDPOINT=http://otelcol:4318/v1/metrics
```

3. Add instrumentation middlewares

config/autoload/middlewares.php:

```php
<?php

declare(strict_types=1);

use Hyperf\OpenTelemetry\Middleware\MetricMiddleware;
use Hyperf\OpenTelemetry\Middleware\TraceMiddleware;

return [
    'http' => [
        MetricMiddleware::class,
        TraceMiddleware::class,
    ],
];
```

---

## 👨‍💻 Development
Build the image
```shell
make build
```

Install dependencies
```shell
make install
```

Run tests
```shell
make test
```

--- 

## 🤝 Contributing
Please see [CONTRIBUTING](CONTRIBUTING.md) for details.