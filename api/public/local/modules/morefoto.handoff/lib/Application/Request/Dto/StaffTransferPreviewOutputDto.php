<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Dto;

final readonly class StaffTransferPreviewOutputDto
{
    /**
     * @param list<array{
     *     rowId: string,
     *     groupId: string,
     *     childCode: string,
     *     targetCode: string,
     *     hasOrders: bool,
     *     photos: list<array{id: string, code: string, revision: int}>,
     * }> $bundles
     */
    public function __construct(
        public string $targetGroupId,
        public array $bundles,
        public string $signature,
        public bool $hasOrders,
        public int $revision,
    ) {}
}
