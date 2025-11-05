<?php

declare(strict_types=1);

namespace Hyperf\OpenTelemetry\Support;

class Uri
{
    public static function sanitize(string $uri, array $uriMask = []): string
    {
        // UUID v1–v8 with any variant
        $uuid = '/\/(?<=\/)([0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12})(?=\/|$)/i';

        $defaultPatterns = [
            $uuid => '/{uuid}',
            '/\/(?<=\/)[ED]\d{8}\d{12}[0-9a-zA-Z]{11}(?=\/|$)/' => '/{e2e_id}',
            '/\/(?<=\/)[a-f0-9]{40}(?=\/|$)/i' => '/{sha1}',
            '/\/(?<=\/)[A-Z]{3}-?\d[A-Z]?\d{2}(?=\/|$)/i' => '/{license_plate}',
            '/\/(?<=\/)[0-9a-f]{16,24}(?=\/|$)/i' => '/{oid}',
            '/\/(?<=\/)\d{4}-\d{2}-\d{2}(?=\/|$)/' => '/{date}',
            '/\/(?<=\/)\d+(?=\/|$)/' => '/{number}',
        ];

        $patterns = array_merge($defaultPatterns, $uriMask);

        return preg_replace(array_keys($patterns), array_values($patterns), '/' . ltrim($uri, '/'));
    }
}
