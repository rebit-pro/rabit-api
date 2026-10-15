<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Archive\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class GroupProductionResultDto implements ResultDtoInterface
{
    /** @param list<VersionResultDto> $versions */
    public function __construct(
        public string $groupId,
        public string $groupName,
        public string $state,
        public ?string $deadlineAt,
        public ?string $buildFrom,
        public bool $canManage,
        public int $outsideLines,
        public int $outsidePrints,
        public int $waitingOrders,
        public array $versions,
    ) {}
}
