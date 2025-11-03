<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Hyperf\OpenTelemetry\Support\Uri;

/**
 * @internal
 */
class UriTest extends TestCase
{
    public function testSanitizeE2EId()
    {
        $uri = '/E60701190202409271154DY52JQB76B5/';
        $result = Uri::sanitize($uri);
        $this->assertStringContainsString('/{e2e_id}', $result);
    }

    public function testSanitizeSha1()
    {
        $uri = '/da39a3ee5e6b4b0d3255bfef95601890afd80709/';
        $result = Uri::sanitize($uri);
        $this->assertStringContainsString('/{sha1}', $result);
    }

    public function testSanitizeUuid()
    {
        $uri = '/123e4567-e89b-12d3-a456-426614174000/';
        $result = Uri::sanitize($uri);
        $this->assertStringContainsString('/{uuid}', $result);
    }

    public function testSanitizeLicensePlate()
    {
        $uri = '/ABC1A23/';
        $result = Uri::sanitize($uri);
        $this->assertStringContainsString('/{license_plate}', $result);
    }

    public function testSanitizeOid()
    {
        $uri = '/abcdef1234567890abcdef12/';
        $result = Uri::sanitize($uri);
        $this->assertStringContainsString('/{oid}', $result);
    }

    public function testSanitizeDate()
    {
        $uri = '/2024-06-01/';
        $result = Uri::sanitize($uri);
        $this->assertStringContainsString('/{date}', $result);
    }

    public function testSanitizeNumber()
    {
        $uri = '/123456789/';
        $result = Uri::sanitize($uri);
        $this->assertStringContainsString('/{number}', $result);
    }

    public function testSanitizeMultiplePatterns()
    {
        $uri = '/E60701190202409271154DY52JQB76B5/2024-06-01/123e4567-e89b-12d3-a456-426614174000/123456789/';
        $result = Uri::sanitize($uri);
        $this->assertStringContainsString('/{e2e_id}/', $result);
        $this->assertStringContainsString('/{date}/', $result);
        $this->assertStringContainsString('/{uuid}/', $result);
        $this->assertStringContainsString('/{number}', $result);
    }

    public function testSanitizeWithCustomMask()
    {
        $uri = '/custom123/';
        $mask = [
            '/custom\d+/' => '/{custom}',
        ];
        $result = Uri::sanitize($uri, $mask);
        $this->assertStringContainsString('/{custom}', $result);
    }

    public function testSanitizeNoMatch()
    {
        $uri = '/no-match/';
        $result = Uri::sanitize($uri);
        $this->assertSame('/no-match/', $result);
    }

    public function testSanitizeUriWithoutLeadingSlash()
    {
        $uri = '123456789';
        $result = Uri::sanitize($uri);
        $this->assertSame('/{number}', $result);
    }

    public function testSanitizeEmptyUri()
    {
        $uri = '';
        $result = Uri::sanitize($uri);
        $this->assertSame('/', $result);
    }

    public function testSanitizeUuidV1ToV4()
    {
        $uri = '/5c4f7ab3-7849-4422-a262-cd1bfc5ae7ae/';
        $result = Uri::sanitize($uri);
        $this->assertStringContainsString('/{uuid}', $result);
    }

    public function testSanitizeUuidV1ToV5()
    {
        $uri = '/123e4567-e89b-12d3-a456-426614174000/';
        $result = Uri::sanitize($uri);
        $this->assertStringContainsString('/{uuid}', $result);
    }

    public function testSanitizeUuidV6()
    {
        $uri = '/01000000-9f9e-6094-8a9d-08ddf52351e2/';
        $result = Uri::sanitize($uri);
        $this->assertStringContainsString('/{uuid}', $result);
    }

    public function testSanitizeUuidV7()
    {
        $uri = '/01890f28-54c2-7d11-bc99-9bb9d1a9e9af/';
        $result = Uri::sanitize($uri);
        $this->assertStringContainsString('/{uuid}', $result);
    }

    public function testSanitizeUuidV8()
    {
        $uri = '/ffffffff-ffff-8fff-9fff-ffffffffffff/';
        $result = Uri::sanitize($uri);
        $this->assertStringContainsString('/{uuid}', $result);
    }

}
