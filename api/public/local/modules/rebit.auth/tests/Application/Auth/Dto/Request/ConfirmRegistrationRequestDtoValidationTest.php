<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Application\Auth\Dto\Request;

use PHPUnit\Framework\TestCase;
use Rebit\Auth\Application\Auth\Dto\Request\ConfirmRegistrationRequestDto;
use Rebit\Share\Infrastructure\Exception\ValidationHttpException;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;

/**
 * @internal
 */
final class ConfirmRegistrationRequestDtoValidationTest extends TestCase
{
    /**
     * @throws ValidationHttpException
     */
    public function testMapsValidRequest(): void
    {
        $dto = ArrayToDtoMapper::map(
            [
                'email' => 'user@example.com',
                'code' => '123456',
            ],
            ConfirmRegistrationRequestDto::class,
        );

        self::assertInstanceOf(ConfirmRegistrationRequestDto::class, $dto);
        self::assertSame('123456', $dto->code);
    }

    public function testThrowsValidationExceptionForInvalidCode(): void
    {
        $this->expectException(ValidationHttpException::class);
        $this->expectExceptionMessage('[code] Код подтверждения должен содержать 6 цифр.');

        ArrayToDtoMapper::map(
            [
                'email' => 'user@example.com',
                'code' => '12ab',
            ],
            ConfirmRegistrationRequestDto::class,
        );
    }
}
