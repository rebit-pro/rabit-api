<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Staff\Result;

use Morefoto\Access\Application\Staff\Dto\StaffPageOutputDto;
use Morefoto\Access\Presentation\Staff\Result\Dto\StaffListResultDto;

final readonly class StaffListResultMapper
{
    public function list(StaffPageOutputDto $output): StaffListResultDto
    {
        return new StaffListResultDto(items: $output->items);
    }

    /**
     * @return array{
     *     page: int,
     *     pageSize: int,
     *     total: int,
     *     totalPages: int,
     *     summary: array{byAccountStatus: array<string, int>, byRole: array<string, int>},
     * }
     */
    public function meta(StaffPageOutputDto $output): array
    {
        return [
            'page' => $output->page,
            'pageSize' => $output->pageSize,
            'total' => $output->total,
            'totalPages' => (int)ceil($output->total / $output->pageSize),
            'summary' => ['byAccountStatus' => $output->byAccountStatus, 'byRole' => $output->byRole],
        ];
    }
}
