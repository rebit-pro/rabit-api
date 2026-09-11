<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\Dto\Request;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RequestRegistrationCodeRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email обязателен.')]
        #[Assert\Email(message: 'Некорректный email.')]
        public string $email,
        #[Assert\NotBlank(message: 'Пароль обязателен.')]
        #[Assert\Length(
            min: 6,
            minMessage: 'Пароль должен содержать минимум 6 символов.',
        )]
        public string $password,
    ) {}
}
