<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class StaffTransferPreviewResultDto implements ResultDtoInterface
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
        public string $targetGroupName,
        public array $bundles,
        public string $signature,
        public bool $hasOrders,
        public int $revision,
    ) {}
}
