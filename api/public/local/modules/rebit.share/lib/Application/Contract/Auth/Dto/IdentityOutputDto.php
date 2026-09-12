<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Auth\Dto;

final readonly class IdentityOutputDto
{
    public function __construct(public int $id, public string $name, public string $email) {}
}
