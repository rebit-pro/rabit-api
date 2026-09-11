<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\Dto\Request;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class LoginCaptchaRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[Assert\NotBlank(message: 'lot_number обязателен.')]
        public string $lot_number,
        #[Assert\NotBlank(message: 'captcha_output обязателен.')]
        public string $captcha_output,
        #[Assert\NotBlank(message: 'pass_token обязателен.')]
        public string $pass_token,
        #[Assert\NotBlank(message: 'gen_time обязателен.')]
        public string $gen_time,
    ) {}
}
