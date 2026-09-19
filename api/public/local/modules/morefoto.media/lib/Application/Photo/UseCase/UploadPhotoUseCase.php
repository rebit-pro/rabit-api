<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\UseCase;

use Morefoto\Media\Application\Photo\Contract\MediaPublisherInterface;
use Morefoto\Media\Application\Photo\Contract\PrivatePhotoStorageInterface;
use Morefoto\Media\Application\Photo\Dto\UploadPhotoOutputDto;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Morefoto\Media\Infrastructure\File\PhotoFileInspector;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;

final readonly class UploadPhotoUseCase
{
    public function __construct(
        private MediaScopeInterface $scopes,
        private AccessGuardInterface $access,
        private PhotoFileInspector $inspector,
        private PrivatePhotoStorageInterface $storage,
        private PhotoRepository $photos,
        private MediaPublisherInterface $publisher,
    ) {}

    public function execute(
        int $userId,
        string $shootId,
        string $groupId,
        string $tmpName,
        string $filename,
        int $bytes,
        ?string $clientFingerprint,
    ): UploadPhotoOutputDto {
        $scope = $this->scopes->resolve($shootId, $groupId);
        $this->access->assertCan($userId, 'media.manage', $scope->institutionId, $scope->groupId);
        if (null === $scope->groupId) {
            throw new \LogicException('Resolved media group is missing.');
        }
        $photo = $this->inspector->inspect($tmpName, $filename, $bytes, $clientFingerprint);
        $originalPath = $this->storage->store($scope->shootPublicId, $photo);
        try {
            $registration = $this->photos->register(
                publicId: Uuid::uuid4()->toString(),
                shootId: $scope->shootId,
                groupId: $scope->groupId,
                photo: $photo,
                originalPath: $originalPath,
            );
        } catch (\Throwable $error) {
            if (!$this->photos->originalPathInUse($originalPath)) {
                $this->storage->delete($originalPath);
            }
            throw $error;
        }
        if ($registration->processingRequired) {
            try {
                $this->publisher->process($registration->publicId);
                $this->photos->markPublished($registration->publicId);
            } catch (\Throwable) {
                // The durable pending marker is replayed by app:media:dispatch-pending.
            }
        }

        return new UploadPhotoOutputDto(
            id: $registration->publicId,
            status: $registration->status,
            revision: $registration->revision,
            existingPhotoId: $registration->existingPhotoId,
        );
    }
}
