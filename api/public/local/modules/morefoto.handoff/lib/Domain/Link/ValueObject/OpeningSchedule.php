<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Link\ValueObject;

use Morefoto\Handoff\Domain\Link\Enum\OpeningStatusEnum;

final readonly class OpeningSchedule
{
    public function __construct(
        public int $groupId,
        public \DateTimeImmutable $openAt,
        public OpeningStatusEnum $status,
        public int $scheduledBy,
        public ?\DateTimeImmutable $openedAt,
        public ?\DateTimeImmutable $manualSentAt,
        public ?int $manualSentBy,
    ) {}
}
