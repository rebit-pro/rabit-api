<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Dto;

use Morefoto\Handoff\Domain\Request\ValueObject\StaffTransferPlan;
use Rebit\Share\Contracts\Media\Dto\ChildSetOutputDto;

final readonly class StaffTransferPlanOutputDto
{
    /**
     * @param list<array{
     *     id: int,
     *     publicId: string,
     *     groupId: int,
     *     groupPublicId: string,
     *     childId: int,
     *     photoIds: list<string>,
     * }> $rows строки заявки в порядке bundles
     * @param array<int, ChildSetOutputDto> $sets
     */
    public function __construct(
        public StaffTransferPlan $plan,
        public int $targetGroupId,
        public string $targetGroupPublicId,
        public string $targetGroupName,
        public array $rows,
        public array $sets,
    ) {}
}
