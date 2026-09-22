<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Link\ValueObject;

use Morefoto\Handoff\Domain\Link\Enum\LinkEventKindEnum;

/** Calendar moments are ISO 8601 strings with an offset; storage keeps them in UTC. */
final readonly class LinkHistoryEntry
{
    public function __construct(
        public int $groupId,
        public LinkEventKindEnum $kind,
        public int $actorId,
        public string $actorName,
        public ?string $signature = null,
        public ?string $sentAt = null,
        public ?string $closesAt = null,
        public ?string $deliveryDueAt = null,
        public ?string $previousSentAt = null,
        public ?string $previousClosesAt = null,
        public ?string $previousDeliveryDueAt = null,
        public ?string $reason = null,
    ) {}
}
