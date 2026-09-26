<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Service;

use Morefoto\Media\Application\Photo\Contract\MediaTransactionInterface;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Размечает принятый кадр кодами детей, переданными вместе с файлом, чтобы загрузка архива по папкам не требовала
 * ручной разметки. Групповой кадр получает все переданные коды сразу; повтор того же файла добавляет только
 * недостающие привязки, а кадр из другой группы не размечается.
 */
final readonly class UploadChildAssignment
{
    public function __construct(
        private MediaTransactionInterface $transaction,
        private MediaMutationRepository $media,
        private MediaScopeInterface $scopes,
    ) {}

    /**
     * @param non-empty-list<string> $childCodes
     *
     * @return list<string> коды, к которым кадр привязан после вызова; пустой, если кадр вне группы загрузки
     */
    public function assign(MediaScopeOutputDto $scope, string $photoId, array $childCodes): array
    {
        if (null === $scope->groupId || null === $scope->groupPublicId) {
            throw new \LogicException('Resolved media group is missing.');
        }
        $shootId = $scope->shootId;
        $groupId = $scope->groupId;
        $shootPublicId = $scope->shootPublicId;
        $groupPublicId = $scope->groupPublicId;

        return $this->transaction->execute(function() use ($shootId, $groupId, $shootPublicId, $groupPublicId, $photoId, $childCodes): array {
            $current = $this->media->lockRevision($shootId);
            // Link delivery takes the same lock: re-read after waiting so no labeling slips into an opened gallery.
            if (!$this->scopes->resolve($shootPublicId, $groupPublicId)->groupEditable) {
                throw new HttpException('GROUP_MEDIA_LOCKED', 409);
            }
            $nativeId = $this->media->groupPhoto($shootId, $groupId, $photoId);
            if (null === $nativeId) {
                return [];
            }
            $changed = false;
            foreach ($childCodes as $code) {
                $changed = $this->media->assign($this->media->child($shootId, $groupId, $code)['id'], [$nativeId]) || $changed;
            }
            if ($changed) {
                $this->media->advanceRevision($shootId, $current);
            }

            return $childCodes;
        });
    }
}
