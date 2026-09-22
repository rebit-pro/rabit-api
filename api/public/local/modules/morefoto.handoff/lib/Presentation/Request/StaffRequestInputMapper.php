<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Request;

use Morefoto\Handoff\Presentation\Request\Dto\CreateStaffRequestRequestDto;
use Morefoto\Handoff\Presentation\Request\Dto\UpdateStaffRequestRequestDto;
use Morefoto\Handoff\Presentation\Request\Dto\ClarifyStaffRequestRequestDto;
use Morefoto\Handoff\Presentation\Request\Dto\ConfirmStaffTransferRequestDto;
use Morefoto\Handoff\Presentation\Request\Dto\StaffRequestListRequestDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestMutationInputDto;
use Morefoto\Handoff\Application\Request\Dto\ClarificationInputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffTransferInputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestListInputDto;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class StaffRequestInputMapper
{
    public function create(CreateStaffRequestRequestDto $request): StaffRequestMutationInputDto
    {
        return StaffRequestValidation::mutation(
            $request->institutionId,
            $request->shootId,
            $request->rows,
            $request->comment,
            null,
            true,
        );
    }

    public function update(UpdateStaffRequestRequestDto $request): StaffRequestMutationInputDto
    {
        return StaffRequestValidation::mutation(
            $request->institutionId,
            $request->shootId,
            $request->rows,
            $request->comment,
            $request->revision,
            false,
        );
    }

    public function clarify(ClarifyStaffRequestRequestDto $request): ClarificationInputDto
    {
        $comment = trim($request->comment);
        if (1 > $request->revision || 5 > mb_strlen($comment) || 500 < mb_strlen($request->comment)) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }

        return new ClarificationInputDto($request->revision, $comment, $request->confirmed);
    }

    public function transfer(ConfirmStaffTransferRequestDto $request): StaffTransferInputDto
    {
        if (!$request->confirmed) {
            throw new HttpException('CONFIRMATION_REQUIRED', 422);
        }
        $reason = trim($request->reason);
        if (1 > $request->revision || 500 < mb_strlen($reason) || 1 !== preg_match('/^[a-f0-9]{64}$/D', $request->signature)) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }

        return new StaffTransferInputDto($reason, $request->revision, $request->signature);
    }

    public function list(StaffRequestListRequestDto $request): StaffRequestListInputDto
    {
        if (null !== $request->status
            && !in_array($request->status, ['submitted', 'clarification', 'transferred'], true)) {
            throw new HttpException('INVALID_STATUS', 422);
        }
        if (1 > $request->page || 1000000 < $request->page || 1 > $request->pageSize || 100 < $request->pageSize) {
            throw new HttpException('INVALID_PAGE', 422);
        }

        return new StaffRequestListInputDto(
            StaffRequestValidation::optionalUuid($request->institutionId),
            StaffRequestValidation::optionalUuid($request->shootId),
            $request->status,
            $request->page,
            $request->pageSize,
        );
    }

    public function key(ClarifyStaffRequestRequestDto|ConfirmStaffTransferRequestDto|CreateStaffRequestRequestDto|UpdateStaffRequestRequestDto $request): IdempotencyKey
    {
        return new IdempotencyKey($request->idempotencyKey);
    }
}
