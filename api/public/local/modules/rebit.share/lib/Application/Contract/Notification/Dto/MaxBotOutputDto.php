<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Notification\Dto;

final readonly class MaxBotOutputDto
{
    public function __construct(
        public int $userId,
        public string $username,
        public string $name,
    ) {}
}
