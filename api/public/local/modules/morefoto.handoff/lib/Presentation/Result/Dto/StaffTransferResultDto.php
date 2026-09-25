<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class StaffTransferResultDto implements ResultDtoInterface
{
    /**
     * @param list<array{
     *     rowId: string,
     *     fromGroupId: string,
     *     fromChildCode: string,
     *     targetGroupId: string,
     *     targetChildCode: string,
     *     photoIds: list<string>,
     * }> $results
     */
    public function __construct(
        public string $id,
        public int $revision,
        public string $status,
        public array $results,
    ) {}
}
