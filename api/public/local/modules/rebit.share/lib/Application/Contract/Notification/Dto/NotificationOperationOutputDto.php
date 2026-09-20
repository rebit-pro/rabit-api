<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Notification\Dto;

final readonly class NotificationOperationOutputDto
{
    public function __construct(
        public string $id,
        public string $status,
        public int $attempts,
        public int $maxAttempts,
        public ?string $nextAttemptAt = null,
        public ?string $acceptedAt = null,
    ) {}
}
