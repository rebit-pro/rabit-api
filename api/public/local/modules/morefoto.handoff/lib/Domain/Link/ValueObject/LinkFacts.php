<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Link\ValueObject;

/** Server facts that decide whether a group gallery may be handed over. */
final readonly class LinkFacts
{
    /** @param list<array{0: string, 1: int}> $pendingRequests public ID and revision of unfinished staff requests */
    public function __construct(
        public string $groupId,
        public string $groupName,
        public string $groupKind,
        public string $shootId,
        public string $institutionId,
        public ?int $teacherId,
        public int $readyPhotos,
        public int $processingPhotos,
        public int $unassignedPhotos,
        public string $materialsFingerprint,
        public int $activeProducts,
        public string $salesFingerprint,
        public array $pendingRequests,
    ) {}
}
