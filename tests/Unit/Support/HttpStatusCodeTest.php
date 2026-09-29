<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Hyperf\HttpMessage\Exception\HttpException;
use Hyperf\OpenTelemetry\Support\HttpStatusCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * @internal
 */
class HttpStatusCodeTest extends TestCase
{
    #[DataProvider('statusProvider')]
    public function testFromException(Throwable $exception, int $expected): void
    {
        $this->assertSame($expected, HttpStatusCode::fromException($exception));
    }

    public static function statusProvider(): array
    {
        return [
            'Validation-like public status' => [
                new class('The given data was invalid.') extends RuntimeException {
                    public int $status = 422;
                },
                422,
            ],
            'Public getStatusCode' => [
                new class(409, 'Conflict') extends RuntimeException {
                    public function __construct(private readonly int $statusCode, string $message)
                    {
                        parent::__construct($message);
                    }

                    public function getStatusCode(): int
                    {
                        return $this->statusCode;
                    }
                },
                409,
            ],
            'HttpException' => [new HttpException(404, 'Not Found'), 404],
            'Plain runtime exception' => [new RuntimeException('Boom'), 500],
            'Exception code is ignored' => [new RuntimeException('Boom', 422), 500],
            'Non-int status property' => [
                new class('Boom') extends RuntimeException {
                    public string $status = '422';
                },
                500,
            ],
            'Status property out of range' => [
                new class('Boom') extends RuntimeException {
                    public int $status = 99;
                },
                500,
            ],
            'Protected status property' => [
                new class('Boom') extends RuntimeException {
                    protected int $status = 422;
                },
                500,
            ],
            'Uninitialized status property' => [
                new class('Boom') extends RuntimeException {
                    public int $status;
                },
                500,
            ],
            'Non-int getStatusCode' => [
                new class('Boom') extends RuntimeException {
                    public function getStatusCode(): mixed
                    {
                        return '422';
                    }
                },
                500,
            ],
            'getStatusCode out of range' => [
                new class('Boom') extends RuntimeException {
                    public function getStatusCode(): int
                    {
                        return 99;
                    }
                },
                500,
            ],
            'Private getStatusCode' => [
                new class('Boom') extends RuntimeException {
                    private function getStatusCode(): int
                    {
                        return 422;
                    }
                },
                500,
            ],
            'getStatusCode wins over status property' => [
                new class('Conflict') extends RuntimeException {
                    public int $status = 422;

                    public function getStatusCode(): int
                    {
                        return 409;
                    }
                },
                409,
            ],
            'Invalid HttpException status falls through' => [
                new class('Broken') extends HttpException {
                    public function __construct(string $message)
                    {
                        parent::__construct(400, $message);
                    }

                    public function getStatusCode(): int
                    {
                        return 0;
                    }
                },
                500,
            ],
        ];
    }

    #[DataProvider('statusOrCodeProvider')]
    public function testFromExceptionOrCode(Throwable $exception, int $expected): void
    {
        $this->assertSame($expected, HttpStatusCode::fromExceptionOrCode($exception));
    }

    public static function statusOrCodeProvider(): array
    {
        return [
            'Validation-like public status' => [
                new class('The given data was invalid.') extends RuntimeException {
                    public int $status = 422;
                },
                422,
            ],
            'Known HTTP exception code' => [new RuntimeException('Boom', 422), 422],
            'Unknown exception code' => [new RuntimeException('Boom', 1000), 500],
            'Default exception code' => [new RuntimeException('Boom'), 500],
            'HttpException beats exception code' => [new HttpException(404, 'Not Found', 500), 404],
            'Server error code' => [new RuntimeException('Boom', 503), 503],
        ];
    }
}
