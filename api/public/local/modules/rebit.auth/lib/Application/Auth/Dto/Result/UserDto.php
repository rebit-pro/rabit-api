<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\Dto\Result;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class UserDto implements ResultDtoInterface
{
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
    ) {}
}
