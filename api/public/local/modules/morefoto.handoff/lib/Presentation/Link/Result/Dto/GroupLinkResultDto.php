<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Link\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class GroupLinkResultDto implements ResultDtoInterface
{
    /**
     * @param list<string>             $problems
     * @param list<LinkEventResultDto> $history
     */
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
        public ?string $galleryToken,
        public string $referenceNow,
        public array $history,
    ) {}
}
