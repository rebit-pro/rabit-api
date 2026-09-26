<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Photo;

use Morefoto\Media\Application\Photo\Dto\AssignPhotosInputDto;
use Morefoto\Media\Application\Photo\Dto\DeletePhotosInputDto;
use Morefoto\Media\Application\Photo\Dto\SetCoverInputDto;
use Morefoto\Media\Application\Photo\Dto\UploadPhotoInputDto;
use Morefoto\Media\Domain\Photo\ValueObject\IdempotencyKey;
use Morefoto\Media\Presentation\Photo\Dto\AssignPhotosRequestDto;
use Morefoto\Media\Presentation\Photo\Dto\DeleteGroupPhotosRequestDto;
use Morefoto\Media\Presentation\Photo\Dto\SetGroupCoverRequestDto;
use Morefoto\Media\Presentation\Photo\Dto\UploadPhotoRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

/** Проверяет и нормализует запросы MED-03, MED-05, MED-06 и удаления кадров до входа сценариев с прежними кодами ошибок. */
final readonly class PhotoInputMapper
{
    /** Коды детей через запятую: один для кадра ребёнка, все дети группы для группового кадра. */
    public const string CHILD_CODES_PATTERN = '/^[A-Z]{1,3}(,[A-Z]{1,3}){0,99}$/D';
    private const int MAX_PHOTOS_PER_REQUEST = 100;

    public function upload(UploadPhotoRequestDto $request): UploadPhotoInputDto
    {
        return new UploadPhotoInputDto(
            shootId: $request->shootId,
            groupId: $request->groupId,
            tmpName: $request->tmpName,
            filename: $request->filename,
            bytes: $request->bytes,
            clientFingerprint: null === $request->fingerprint || '' === $request->fingerprint ? null : strtolower($request->fingerprint),
            childCodes: null === $request->childCodes ? [] : array_values(array_unique(explode(',', $request->childCodes))),
        );
    }

    public function assignment(AssignPhotosRequestDto $request): AssignPhotosInputDto
    {
        if (1 !== preg_match(PhotoListInputMapper::ID_PATTERN, $request->shootId)
            || 1 > $request->revision
            || [] === $request->photoIds || self::MAX_PHOTOS_PER_REQUEST < count($request->photoIds)
            || 1 !== preg_match('/^[A-Z]{1,3}$/D', $request->childCode)) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }

        return new AssignPhotosInputDto(
            shootId: $request->shootId,
            revision: $request->revision,
            photoIds: $this->photoIds($request->photoIds),
            childCode: $request->childCode,
        );
    }

    public function deletion(DeleteGroupPhotosRequestDto $request): DeletePhotosInputDto
    {
        if (1 > $request->revision || [] === $request->photoIds || self::MAX_PHOTOS_PER_REQUEST < count($request->photoIds)) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }

        return new DeletePhotosInputDto(revision: $request->revision, photoIds: $this->photoIds($request->photoIds));
    }

    public function cover(SetGroupCoverRequestDto $request): SetCoverInputDto
    {
        if (1 > $request->revision || 1 !== preg_match(PhotoListInputMapper::ID_PATTERN, $request->photoId)) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }

        return new SetCoverInputDto(revision: $request->revision, photoId: $request->photoId);
    }

    public function key(string $idempotencyKey): IdempotencyKey
    {
        return new IdempotencyKey($idempotencyKey);
    }

    /**
     * @param non-empty-array<mixed> $values
     *
     * @return non-empty-list<string>
     */
    private function photoIds(array $values): array
    {
        $photoIds = [];
        foreach ($values as $photoId) {
            if (!is_string($photoId) || 1 !== preg_match(PhotoListInputMapper::ID_PATTERN, $photoId) || isset($photoIds[$photoId])) {
                throw new HttpException('INVALID_PHOTO_IDS', 422);
            }
            $photoIds[$photoId] = true;
        }

        return array_keys($photoIds);
    }
}
