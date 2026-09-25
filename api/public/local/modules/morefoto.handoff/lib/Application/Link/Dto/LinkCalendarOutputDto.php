<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Dto;

final readonly class LinkCalendarOutputDto
{
    public function __construct(
        public int $revision,
        public string $sentAt,
        public string $closesAt,
        public string $deliveryAt,
    ) {}
}
