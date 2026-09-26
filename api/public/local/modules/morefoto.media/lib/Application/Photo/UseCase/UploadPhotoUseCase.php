<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\UseCase;

use Morefoto\Media\Application\Photo\Contract\MediaPublisherInterface;
use Morefoto\Media\Application\Photo\Contract\OriginalFileLockInterface;
use Morefoto\Media\Application\Photo\Contract\PrivatePhotoStorageInterface;
use Morefoto\Media\Application\Photo\Dto\PhotoRegistration;
use Morefoto\Media\Application\Photo\Dto\UploadPhotoInputDto;
use Morefoto\Media\Application\Photo\Dto\UploadPhotoOutputDto;
use Morefoto\Media\Application\Photo\Service\UploadChildAssignment;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Morefoto\Media\Infrastructure\File\PhotoFileInspector;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Принимает приватный оригинал в редактируемую группу и регистрирует его без дубля по содержимому в съёмке.
 *
 * Переданные коды детей размечают кадр при приёме — так загрузка архива по папкам обходится без ручной разметки.
 * Сразу ставит подготовку превью в очередь; при сбое публикации оставляет задание pending для dispatcher и пишет
 * в журнал этап и класс ошибки, а длительности приёма позволяют отличить задержку сервера от задержки очереди.
 * Запись оригинала и регистрация идут под блокировкой его пути, чтобы удаление кадра с тем же содержимым не стёрло файл.
 */
final readonly class UploadPhotoUseCase
{
    public function __construct(
        private MediaScopeInterface $scopes,
        private AccessGuardInterface $access,
        private PhotoFileInspector $inspector,
        private PrivatePhotoStorageInterface $storage,
        private OriginalFileLockInterface $originals,
        private PhotoRepository $photos,
        private MediaPublisherInterface $publisher,
        private LoggerInterface $logger,
        private UploadChildAssignment $assignment,
    ) {}

    public function execute(int $userId, UploadPhotoInputDto $input): UploadPhotoOutputDto
    {
        $scope = $this->scopes->resolve($input->shootId, $input->groupId);
        $this->access->assertCan($userId, 'media.manage', $scope->institutionId, $scope->groupId);
        if (null === $scope->groupId) {
            throw new \LogicException('Resolved media group is missing.');
        }
        if (!$scope->groupEditable) {
            throw new HttpException('GROUP_MEDIA_LOCKED', 409);
        }
        $started = hrtime(true);
        $photo = $this->inspector->inspect($input->tmpName, $input->filename, $input->bytes, $input->clientFingerprint);
        $inspected = hrtime(true);
        $stored = $inspected;
        $groupId = $scope->groupId;
        // A deletion of the photo that owned this path removes the file under the same lock, never between store and register.
        $registration = $this->originals->synchronized(
            $this->storage->path($scope->shootPublicId, $photo),
            function() use ($scope, $groupId, $photo, &$stored): PhotoRegistration {
                $originalPath = $this->storage->store($scope->shootPublicId, $photo);
                $stored = hrtime(true);
                try {
                    return $this->photos->register(
                        publicId: Uuid::uuid4()->toString(),
                        shootId: $scope->shootId,
                        groupId: $groupId,
                        photo: $photo,
                        originalPath: $originalPath,
                    );
                } catch (\Throwable $error) {
                    if (!$this->photos->originalPathInUse($originalPath)) {
                        $this->storage->delete($originalPath);
                    }
                    throw $error;
                }
            },
        );
        $registered = hrtime(true);
        $published = $registration->processingRequired && $this->publish($registration->publicId, $registration->revision);
        $queued = hrtime(true);
        // A duplicate adds the codes to the photo that owns the content, so a repeated archive completes its labels.
        $childCodes = [] === $input->childCodes ? [] : $this->assignment->assign(
            $scope,
            $registration->existingPhotoId ?? $registration->publicId,
            $input->childCodes,
        );
        $this->logger->info('Photo upload accepted.', [
            'photoId' => $registration->publicId,
            'photoStatus' => $registration->status,
            'bytes' => $input->bytes,
            'published' => $published,
            'inspectMs' => self::milliseconds($started, $inspected),
            'storeMs' => self::milliseconds($inspected, $stored),
            'added' => count($childCodes),
            'registerMs' => self::milliseconds($stored, $registered),
            'publishMs' => self::milliseconds($registered, $queued),
            'assignMs' => self::milliseconds($queued, hrtime(true)),
        ]);

        return new UploadPhotoOutputDto(
            id: $registration->publicId,
            status: $registration->status,
            revision: $registration->revision,
            existingPhotoId: $registration->existingPhotoId,
            childCodes: $childCodes,
        );
    }

    /**
     * The durable pending marker is replayed by app:media:dispatch-pending, so a failure here must not fail the upload.
     */
    private function publish(string $photoId, int $revision): bool
    {
        $stage = 'publish';
        try {
            $this->publisher->process($photoId, $revision);
            $stage = 'markPublished';
            $this->photos->markPublished($photoId);

            return true;
        } catch (\Throwable $error) {
            $this->logger->warning('Photo job remains pending after immediate publish failure.', [
                'photoId' => $photoId,
                'revision' => $revision,
                'stage' => $stage,
                'exception' => $error::class,
                'previous' => null === $error->getPrevious() ? null : $error->getPrevious()::class,
            ]);

            return false;
        }
    }

    private static function milliseconds(float|int $from, float|int $to): int
    {
        return (int)(($to - $from) / 1_000_000);
    }
}
