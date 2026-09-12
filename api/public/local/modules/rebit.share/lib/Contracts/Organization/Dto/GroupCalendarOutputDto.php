<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class GroupCalendarOutputDto
{
    public function __construct(
        public string $timezone,
        public ?string $sentAt,
        public ?string $closesAt,
        public ?string $deliveryDueAt,
        public string $status,
    ) {}
}
