<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Result;

use Morefoto\Handoff\Application\Request\Dto\StaffRequestOutputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestListOutputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestMutationOutputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffTransferOutputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffTransferPreviewOutputDto;
use Morefoto\Handoff\Presentation\Result\Dto\StaffRequestResultDto;
use Morefoto\Handoff\Presentation\Result\Dto\StaffRequestListResultDto;
use Morefoto\Handoff\Presentation\Result\Dto\StaffRequestMutationResultDto;
use Morefoto\Handoff\Presentation\Result\Dto\StaffTransferPreviewResultDto;
use Morefoto\Handoff\Presentation\Result\Dto\StaffTransferResultDto;

final readonly class StaffRequestResultMapper
{
    public function detail(StaffRequestOutputDto $output): StaffRequestResultDto
    {
        return new StaffRequestResultDto(
            id: $output->id,
            institutionId: $output->institutionId,
            shootId: $output->shootId,
            createdBy: $output->createdBy,
            createdByName: $output->createdByName,
            createdAt: $output->createdAt,
            revision: $output->revision,
            status: $output->status,
            rows: $output->rows,
            comment: $output->comment,
            history: $output->history,
            results: $output->results,
            staffEligibility: $output->staffEligibility,
        );
    }

    public function list(StaffRequestListOutputDto $output): StaffRequestListResultDto
    {
        $items = [];
        foreach ($output->items as $item) {
            $items[] = $this->detail($item);
        }

        return new StaffRequestListResultDto(items: $items, scope: $output->scope);
    }

    /** @return array{page: int, pageSize: int, total: int, totalPages: int} */
    public function meta(StaffRequestListOutputDto $output): array
    {
        return ['page' => $output->page, 'pageSize' => $output->pageSize, 'total' => $output->total, 'totalPages' => $output->totalPages];
    }

    public function mutation(StaffRequestMutationOutputDto $output): StaffRequestMutationResultDto
    {
        return new StaffRequestMutationResultDto(id: $output->id, revision: $output->revision, status: $output->status);
    }

    public function preview(StaffTransferPreviewOutputDto $output): StaffTransferPreviewResultDto
    {
        return new StaffTransferPreviewResultDto(
            targetGroupId: $output->targetGroupId,
            bundles: $output->bundles,
            signature: $output->signature,
            hasOrders: $output->hasOrders,
            revision: $output->revision,
        );
    }

    public function transfer(StaffTransferOutputDto $output): StaffTransferResultDto
    {
        return new StaffTransferResultDto(id: $output->id, revision: $output->revision, status: $output->status, results: $output->results);
    }

    public function location(StaffRequestMutationOutputDto $output): string
    {
        return '/api/v1/staff-requests/' . $output->id;
    }
}
