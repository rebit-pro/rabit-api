<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Link\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class LinkEventResultDto implements ResultDtoInterface
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
