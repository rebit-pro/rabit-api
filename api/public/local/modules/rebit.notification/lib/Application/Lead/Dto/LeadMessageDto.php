<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Lead\Dto;

/**
 * Очищенные данные заявки для доставки в канал уведомления.
 */
final readonly class LeadMessageDto
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $description,
        public string $page,
        public string $email = '',
    ) {}
}
