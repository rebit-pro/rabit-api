<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Dto;

final readonly class GroupLinkSummaryOutputDto
{
    /** @param list<string> $problems */
    public function __construct(
        public string $groupId,
        public string $name,
        public string $kind,
        public string $institutionId,
        public string $institutionName,
        public string $shootId,
        public string $shootName,
        public int $revision,
        public string $signature,
        public bool $prepared,
        public array $problems,
        public string $state,
        public string $timezone,
        public ?string $sentAt,
        public ?string $closesAt,
        public ?string $deliveryAt,
        public int $photoCount,
        public int $childCount,
    ) {}
}
