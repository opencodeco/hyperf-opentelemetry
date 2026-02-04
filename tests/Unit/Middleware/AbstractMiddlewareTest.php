<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Hyperf\Contract\ConfigInterface;
use Hyperf\OpenTelemetry\Instrumentation;
use Hyperf\OpenTelemetry\Middleware\AbstractMiddleware;
use Hyperf\OpenTelemetry\Switcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 */
class AbstractMiddlewareTest extends TestCase
{
    private AbstractMiddleware $middleware;

    private ConfigInterface $config;

    private Instrumentation $instrumentation;

    private Switcher $switcher;

    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(ConfigInterface::class);
        $this->instrumentation = $this->createMock(Instrumentation::class);
        $this->switcher = $this->createMock(Switcher::class);
        $this->request = $this->createMock(ServerRequestInterface::class);

        $this->switcher->method('isTracingEnabled')->willReturn(false);
        $this->switcher->method('isMetricsEnabled')->willReturn(false);

        $this->middleware = new class($this->config, $this->instrumentation, $this->switcher) extends AbstractMiddleware {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                return $handler->handle($request);
            }

            protected function featureName(): string
            {
                return 'feature';
            }

            public function testShouldIgnorePath(string $path): bool
            {
                return $this->shouldIgnorePath($path);
            }

            public function testTransformHeaders(string $type, array $headers): array
            {
                return $this->transformHeaders($type, $headers);
            }

            public function testGetRequestIP(ServerRequestInterface $request): string
            {
                return $this->getRequestIP($request);
            }
        };
    }

    public function testShouldIgnorePath(): void
    {
        $this->config->method('get')
            ->with('open-telemetry.instrumentation.features.client_request.options.ignore_paths', [])
            ->willReturn(['/^\/health$/']);

        $this->assertFalse($this->middleware->testShouldIgnorePath('/api/users'));
        $this->assertTrue($this->middleware->testShouldIgnorePath('/health'));
    }

    #[DataProvider('invalidIgnoredPathProvider')]
    public function testShouldIgnorePathReturnsFalseWhenConfigIsInvalid(array $paths): void
    {
        $this->config->method('get')
            ->with('open-telemetry.instrumentation.features.client_request.options.ignore_paths', [])
            ->willReturn($paths);

        $this->assertFalse($this->middleware->testShouldIgnorePath('/api/users'));
        $this->assertFalse($this->middleware->testShouldIgnorePath('/health'));
    }

    public static function invalidIgnoredPathProvider(): array
    {
        return [
            'Empty' => [[]],
            'Null' => [[null]],
            'Empty String' => [['']],
            'Number' => [[1]],
        ];
    }

    public function testTransformHeaders(): void
    {
        $headers = [
            'Content-Type' => ['application/json'],
            'Authorization' => ['Bearer token123'],
        ];

        $this->config->method('get')
            ->with('open-telemetry.instrumentation.features.client_request.options.headers.request', ['*'])
            ->willReturn(['*']);

        $result = $this->middleware->testTransformHeaders('request', $headers);

        $expected = [
            'http.request.header.content-type' => 'application/json',
            'http.request.header.authorization' => 'Bearer token123',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testTransformHeadersWithFilteredHeaders(): void
    {
        $headers = [
            'Content-Type' => ['application/json'],
            'Authorization' => ['Bearer token123'],
            'X-Custom-Header' => ['custom-value'],
        ];

        $this->config->method('get')
            ->with('open-telemetry.instrumentation.features.client_request.options.headers.response', ['*'])
            ->willReturn(['content-type', 'x-*']);

        $result = $this->middleware->testTransformHeaders('response', $headers);

        $expected = [
            'http.response.header.content-type' => 'application/json',
            'http.response.header.x-custom-header' => 'custom-value',
        ];

        $this->assertEquals($expected, $result);
    }

    #[DataProvider('requestIpProvider')]
    public function testGetRequestIP(array $headers, array $server, string $expected): void
    {
        $this->request->method('getHeaderLine')
            ->willReturnMap($headers);

        $this->request->method('getServerParams')->willReturn($server);

        $result = $this->middleware->testGetRequestIP($this->request);

        $this->assertEquals($expected, $result);
    }

    public static function requestIpProvider(): array
    {
        return [
            'x-forwarded-for' => [
                [
                    ['x-forwarded-for', '192.168.1.100, 10.0.0.1'],
                    ['remote-host', ''],
                    ['x-real-ip', ''],
                ],
                [],
                '192.168.1.100',
            ],
            'Remote Host' => [
                [
                    ['x-forwarded-for', ''],
                    ['remote-host', '192.168.1.200'],
                    ['x-real-ip', ''],
                ],
                [],
                '192.168.1.200',
            ],
            'Real IP' => [
                [
                    ['x-forwarded-for', ''],
                    ['remote-host', ''],
                    ['x-real-ip', '192.168.1.300'],
                ],
                [],
                '192.168.1.300',
            ],
            'Server Params' => [
                [
                    ['x-forwarded-for', ''],
                    ['remote-host', ''],
                    ['x-real-ip', ''],
                ],
                ['remote_addr' => '192.168.1.400'],
                '192.168.1.400',
            ],
            'No IP' => [
                [
                    ['x-forwarded-for', ''],
                    ['remote-host', ''],
                    ['x-real-ip', ''],
                ],
                [],
                '',
            ],
            'Multiple' => [
                [
                    ['x-forwarded-for', '203.0.113.1, 198.51.100.1, 192.0.2.1'],
                    ['remote-host', ''],
                    ['x-real-ip', ''],
                ],
                [],
                '203.0.113.1',
            ],
        ];
    }
}
