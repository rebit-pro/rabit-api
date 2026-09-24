<?php

declare(strict_types=1);

namespace Rebit\Auth\Presentation\Access\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody]
#[StrictRequest]
final readonly class PasswordResetRequestDto implements RequestDtoInterface
{
    public function __construct(
        public string $email,
    ) {}
}
