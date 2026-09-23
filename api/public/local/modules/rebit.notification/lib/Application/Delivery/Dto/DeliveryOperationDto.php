<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Delivery\Dto;

use Rebit\Share\Application\Contract\Notification\Dto\NotificationOperationOutputDto;

final readonly class DeliveryOperationDto
{
    public function __construct(
        public string $id,
        public string $channel,
        public string $recipient,
        public string $subject,
        public string $body,
        public string $status,
        public int $attempts,
        public int $maxAttempts,
        public ?string $nextAttemptAt = null,
        public ?string $acceptedAt = null,
        public ?string $bodyHtml = null,
    ) {}

    public function output(): NotificationOperationOutputDto
    {
        return new NotificationOperationOutputDto(
            id: $this->id,
            status: $this->status,
            attempts: $this->attempts,
            maxAttempts: $this->maxAttempts,
            nextAttemptAt: $this->nextAttemptAt,
            acceptedAt: $this->acceptedAt,
        );
    }
}
