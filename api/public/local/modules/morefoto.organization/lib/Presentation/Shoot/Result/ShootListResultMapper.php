<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Shoot\Result;

use Morefoto\Organization\Application\Structure\Dto\ShootListOutputDto;
use Morefoto\Organization\Presentation\Shoot\Result\Dto\ShootListItemResultDto;
use Morefoto\Organization\Presentation\Shoot\Result\Dto\ShootListResultDto;

final readonly class ShootListResultMapper
{
    public function list(ShootListOutputDto $output): ShootListResultDto
    {
        $items = [];
        foreach ($output->items as $item) {
            $items[] = new ShootListItemResultDto(
                id: $item->id,
                institutionId: $item->institutionId,
                institutionName: $item->institutionName,
                name: $item->name,
                date: $item->date,
                revision: $item->revision,
                groupCount: $item->groupCount,
                openGroupCount: $item->openGroupCount,
            );
        }

        return new ShootListResultDto(items: $items);
    }

    /** @return array{page: int, pageSize: int, total: int, totalPages: int} */
    public function meta(ShootListOutputDto $output): array
    {
        return ['page' => $output->page, 'pageSize' => $output->pageSize, 'total' => $output->total, 'totalPages' => $output->totalPages];
    }
}
