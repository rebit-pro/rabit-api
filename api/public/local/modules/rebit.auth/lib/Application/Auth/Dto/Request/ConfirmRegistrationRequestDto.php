<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\Dto\Request;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ConfirmRegistrationRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email обязателен.')]
        #[Assert\Email(message: 'Некорректный email.')]
        public string $email,
        #[Assert\NotBlank(message: 'Код подтверждения обязателен.')]
        #[Assert\Length(
            min: 6,
            max: 6,
            exactMessage: 'Код подтверждения должен содержать 6 цифр.',
        )]
        #[Assert\Regex(
            pattern: '/^\d{6}$/',
            message: 'Код подтверждения должен содержать 6 цифр.',
        )]
        public string $code,
    ) {}
}
