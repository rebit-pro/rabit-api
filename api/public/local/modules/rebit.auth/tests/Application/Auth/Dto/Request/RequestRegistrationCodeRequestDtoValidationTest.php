<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Application\Auth\Dto\Request;

use PHPUnit\Framework\TestCase;
use Rebit\Auth\Application\Auth\Dto\Request\RequestRegistrationCodeRequestDto;
use Rebit\Share\Infrastructure\Exception\ValidationHttpException;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;

/**
 * @internal
 */
final class RequestRegistrationCodeRequestDtoValidationTest extends TestCase
{
    /**
     * @throws ValidationHttpException
     */
    public function testMapsValidRequest(): void
    {
        $dto = ArrayToDtoMapper::map(
            [
                'email' => 'user@example.com',
                'password' => 'secret123',
            ],
            RequestRegistrationCodeRequestDto::class,
        );

        self::assertInstanceOf(RequestRegistrationCodeRequestDto::class, $dto);
        self::assertSame('user@example.com', $dto->email);
        self::assertSame('secret123', $dto->password);
    }

    public function testThrowsValidationExceptionForShortPassword(): void
    {
        $this->expectException(ValidationHttpException::class);
        $this->expectExceptionMessage('[password] Пароль должен содержать минимум 6 символов.');

        ArrayToDtoMapper::map(
            [
                'email' => 'user@example.com',
                'password' => '12345',
            ],
            RequestRegistrationCodeRequestDto::class,
        );
    }
}
