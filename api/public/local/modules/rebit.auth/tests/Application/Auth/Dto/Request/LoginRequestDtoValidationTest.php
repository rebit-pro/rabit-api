<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Application\Auth\Dto\Request;

use PHPUnit\Framework\TestCase;
use Rebit\Auth\Application\Auth\Dto\Request\LoginRequestDto;
use Rebit\Share\Infrastructure\Exception\ValidationHttpException;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;

/**
 * @internal
 */
final class LoginRequestDtoValidationTest extends TestCase
{
    /**
     * @throws ValidationHttpException
     */
    public function testMapsNestedCaptchaPayloadToDto(): void
    {
        $dto = ArrayToDtoMapper::map(
            [
                'email' => 'user@example.com',
                'password' => 'secret123',
                'captcha' => [
                    'lot_number' => 'lot-number',
                    'captcha_output' => 'captcha-output',
                    'pass_token' => 'pass-token',
                    'gen_time' => '1710000000',
                ],
            ],
            LoginRequestDto::class,
        );

        self::assertInstanceOf(LoginRequestDto::class, $dto);
        self::assertNotNull($dto->captcha);
        self::assertSame('lot-number', $dto->captcha->lot_number);
        self::assertSame('captcha-output', $dto->captcha->captcha_output);
        self::assertSame('pass-token', $dto->captcha->pass_token);
        self::assertSame('1710000000', $dto->captcha->gen_time);
    }

    public function testThrowsValidationExceptionWhenNestedCaptchaFieldIsBlank(): void
    {
        $this->expectException(ValidationHttpException::class);
        $this->expectExceptionMessage('[captcha.lot_number] lot_number обязателен.');

        ArrayToDtoMapper::map(
            [
                'email' => 'user@example.com',
                'password' => 'secret123',
                'captcha' => [
                    'lot_number' => '',
                    'captcha_output' => 'captcha-output',
                    'pass_token' => 'pass-token',
                    'gen_time' => '1710000000',
                ],
            ],
            LoginRequestDto::class,
        );
    }
}
