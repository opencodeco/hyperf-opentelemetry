<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hyperf\OpenTelemetry\ConfigProvider;
use Hyperf\OpenTelemetry\Factory\CachedInstrumentationFactory;
use Hyperf\OpenTelemetry\Factory\OTelResourceFactory;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ConfigProviderTest extends TestCase
{
    public function testInvokeReturnsExpectedConfig()
    {
        $provider = new ConfigProvider();
        $config = $provider();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('dependencies', $config);
        $this->assertArrayHasKey('listeners', $config);
        $this->assertArrayHasKey('aspects', $config);
        $this->assertArrayHasKey('publish', $config);

        $this->assertSame(
            CachedInstrumentationFactory::class,
            $config['dependencies'][CachedInstrumentation::class]
        );
        $this->assertSame(
            OTelResourceFactory::class,
            $config['dependencies'][ResourceInfo::class]
        );

        $this->assertNotEmpty($config['listeners']);
        $this->assertNotEmpty($config['aspects']);
        $this->assertNotEmpty($config['publish']);
        $this->assertEquals('config', $config['publish'][0]['id']);
    }
}
