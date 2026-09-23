<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Dto;

final readonly class StaffTransferOutputDto
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
