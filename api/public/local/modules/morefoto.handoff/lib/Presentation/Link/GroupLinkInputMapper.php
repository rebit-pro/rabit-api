<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Link;

use Morefoto\Handoff\Application\Link\Dto\GroupLinkListInputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkCorrectionInputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkPreparationInputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkTransmissionInputDto;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Morefoto\Handoff\Presentation\Link\Request\Dto\CorrectGroupLinkDateRequestDto;
use Morefoto\Handoff\Presentation\Link\Request\Dto\GroupLinkListRequestDto;
use Morefoto\Handoff\Presentation\Link\Request\Dto\PrepareGroupLinkRequestDto;
use Morefoto\Handoff\Presentation\Link\Request\Dto\TransmitGroupLinkRequestDto;
use Morefoto\Handoff\Presentation\Request\StaffRequestValidation;
use Rebit\Share\Shared\Exception\HttpException;

/** Validates presentation request DTOs of group links and converts them into Application input DTOs. */
final readonly class GroupLinkInputMapper
{
    private const string MOMENT = 'Y-m-d\TH:i:sP';

    public function list(GroupLinkListRequestDto $request): GroupLinkListInputDto
    {
        if (null !== $request->state && !in_array($request->state, ['preparing', 'open', 'closed'], true)) {
            throw new HttpException('INVALID_STATE', 422);
        }
        if (1 > $request->page || 1000000 < $request->page || 1 > $request->pageSize || 100 < $request->pageSize) {
            throw new HttpException('INVALID_PAGE', 422);
        }

        return new GroupLinkListInputDto(
            StaffRequestValidation::optionalUuid($request->institutionId),
            StaffRequestValidation::optionalUuid($request->shootId),
            $request->state,
            $request->page,
            $request->pageSize,
        );
    }

    public function preparation(PrepareGroupLinkRequestDto $request): LinkPreparationInputDto
    {
        if (!$request->photosReviewed || !$request->conditionsReviewed || !$request->staffReviewed || !$request->confirmed) {
            throw new HttpException('REVIEW_REQUIRED', 422);
        }

        return new LinkPreparationInputDto($this->revision($request->revision), $this->signature($request->signature));
    }

    public function transmission(TransmitGroupLinkRequestDto $request): LinkTransmissionInputDto
    {
        $this->confirmed($request->confirmed);

        return new LinkTransmissionInputDto($this->revision($request->revision), $this->signature($request->signature), $this->sentAt($request->sentAt));
    }

    public function correction(CorrectGroupLinkDateRequestDto $request): LinkCorrectionInputDto
    {
        $this->confirmed($request->confirmed);
        $reason = trim($request->reason);
        if (5 > mb_strlen($reason) || 500 < mb_strlen($reason)) {
            throw new HttpException('INVALID_REASON', 422);
        }

        return new LinkCorrectionInputDto($this->revision($request->revision), $this->signature($request->signature), $this->sentAt($request->sentAt), $reason);
    }

    public function key(CorrectGroupLinkDateRequestDto|PrepareGroupLinkRequestDto|TransmitGroupLinkRequestDto $request): IdempotencyKey
    {
        return new IdempotencyKey($request->idempotencyKey);
    }

    private function revision(int $revision): int
    {
        if (1 > $revision) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }

        return $revision;
    }

    private function signature(string $signature): string
    {
        if (1 !== preg_match('/^[a-f0-9]{64}$/D', $signature)) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }

        return $signature;
    }

    private function confirmed(bool $confirmed): void
    {
        if (!$confirmed) {
            throw new HttpException('CONFIRMATION_REQUIRED', 422);
        }
    }

    /** ISO 8601 with an explicit offset and whole seconds; the round trip rejects impossible calendar dates. */
    private function sentAt(string $value): \DateTimeImmutable
    {
        $moment = \DateTimeImmutable::createFromFormat('!' . self::MOMENT, $value);
        if (false === $moment || $moment->format(self::MOMENT) !== $value) {
            throw new HttpException('INVALID_SENT_AT', 422);
        }

        return $moment;
    }
}
