<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Dto;

final readonly class LinkEventOutputDto
{
    public function __construct(
        public string $kind,
        public int $actorId,
        public string $actorName,
        public string $at,
        public ?string $sentAt,
        public ?string $closesAt,
        public ?string $deliveryAt,
        public ?string $previousSentAt,
        public ?string $previousClosesAt,
        public ?string $previousDeliveryAt,
        public ?string $reason,
    ) {}
}
