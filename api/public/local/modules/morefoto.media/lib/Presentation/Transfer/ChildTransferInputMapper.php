<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Transfer;

use Morefoto\Media\Application\Transfer\Dto\TransferChildInputDto;
use Morefoto\Media\Domain\Photo\ValueObject\IdempotencyKey;
use Morefoto\Media\Presentation\Transfer\Dto\TransferChildRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

/** Проверяет и нормализует тело MED-07 до входа сценария переноса. */
final readonly class ChildTransferInputMapper
{
    public const string UUID_PATTERN = '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D';
    // The idempotency receipt keeps the photo IDs in a TEXT column.
    private const int MAX_PHOTOS = 1000;

    public function transfer(TransferChildRequestDto $request): TransferChildInputDto
    {
        if (1 !== preg_match(self::UUID_PATTERN, $request->fromGroupId)
            || 1 !== preg_match(self::UUID_PATTERN, $request->toGroupId)
            || $request->fromGroupId === $request->toGroupId
            || 1 !== preg_match('/^[A-Z]{1,3}$/D', $request->childCode)
            || 1 !== preg_match('/^[A-Z]{1,3}$/D', $request->targetCode)
            || 1 > $request->revision) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }
        $photoIds = [];
        foreach ($request->expectedPhotoIds as $photoId) {
            if (1 !== preg_match('/^[a-f0-9-]{36}$/D', $photoId) || isset($photoIds[$photoId])) {
                throw new HttpException('INVALID_PHOTO_IDS', 422);
            }
            $photoIds[$photoId] = true;
        }
        if ([] === $photoIds || self::MAX_PHOTOS < count($photoIds)) {
            throw new HttpException('INVALID_PHOTO_IDS', 422);
        }

        return new TransferChildInputDto(
            shootId: $request->shootId,
            fromGroupId: $request->fromGroupId,
            toGroupId: $request->toGroupId,
            childCode: $request->childCode,
            targetCode: $request->targetCode,
            expectedPhotoIds: array_keys($photoIds),
            revision: $request->revision,
        );
    }

    public function key(TransferChildRequestDto $request): IdempotencyKey
    {
        return new IdempotencyKey($request->idempotencyKey);
    }
}
