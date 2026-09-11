<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\Dto\Request;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class LoginRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email обязателен.')]
        #[Assert\Email(message: 'Некорректный email.')]
        public readonly string $email,
        #[Assert\NotBlank(message: 'Пароль обязателен.')]
        public readonly string $password,
        #[Assert\Valid]
        public ?LoginCaptchaRequestDto $captcha = null,
    ) {}
}
