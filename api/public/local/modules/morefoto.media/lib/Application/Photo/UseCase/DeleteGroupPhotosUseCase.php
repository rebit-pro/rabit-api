<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\UseCase;

use Morefoto\Media\Application\Photo\Contract\MediaTransactionInterface;
use Morefoto\Media\Application\Photo\Dto\DeletePhotosInputDto;
use Morefoto\Media\Application\Photo\Dto\DeletionMutationOutputDto;
use Morefoto\Media\Application\Photo\Service\PhotoFileCleaner;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Photo\ValueObject\IdempotencyKey;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\GroupReferenceInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Безвозвратно убирает лишние кадры из группы, пока она готовится и кадры не могли попасть в заказы.
 *
 * Вместе с кадрами снимает их назначения детям и записи повторов, переносит обложку, а файлы удаляет после фиксации транзакции.
 */
final readonly class DeleteGroupPhotosUseCase
{
    public function __construct(
        private MediaTransactionInterface $transaction,
        private MediaMutationRepository $media,
        private GroupReferenceInterface $groups,
        private MediaScopeInterface $scopes,
        private AccessGuardInterface $access,
        private PhotoFileCleaner $files,
    ) {}

    public function execute(int $actorId, string $groupId, IdempotencyKey $key, DeletePhotosInputDto $input): DeletionMutationOutputDto
    {
        $removed = [];
        $output = $this->transaction->execute(function() use ($actorId, $groupId, $key, $input, &$removed): DeletionMutationOutputDto {
            $group = $this->groups->get($groupId);
            $scope = $this->scopes->resolve($group->shootId, $groupId);
            $this->access->assertCan($actorId, 'media.manage', $scope->institutionId, $scope->groupId);
            if (!$scope->groupEditable || null === $scope->groupId) {
                throw new HttpException('GROUP_LOCKED', 409);
            }
            $current = $this->media->lockRevision($scope->shootId);
            // Link delivery takes the same lock: re-read after waiting so no photo disappears from an opened gallery.
            if (!$this->scopes->resolve($group->shootId, $groupId)->groupEditable) {
                throw new HttpException('GROUP_LOCKED', 409);
            }
            $resource = '/groups/' . $groupId . '/photo-deletions';
            $hash = hash('sha256', json_encode([
                'revision' => $input->revision,
                'photoIds' => $input->photoIds,
            ], JSON_THROW_ON_ERROR));
            $stored = $this->media->idempotency($actorId, $resource, $key)->fetch();
            if (false !== $stored) {
                if (!hash_equals((string)$stored['PAYLOAD_HASH'], $hash)) {
                    throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
                }

                return $this->restore((string)$stored['RESULT_JSON']);
            }
            if ($input->revision !== $current) {
                throw new HttpException('REVISION_CONFLICT', 409);
            }
            $removed = $this->media->deletablePhotos($scope->shootId, $scope->groupId, $input->photoIds);
            $this->media->deletePhotos(array_map(static fn(array $photo): int => $photo['id'], $removed));
            $output = new DeletionMutationOutputDto(count($removed), $this->media->advanceRevision($scope->shootId, $current));
            $this->media->saveIdempotency($actorId, $resource, $key, $hash, json_encode([
                'deleted' => $output->deleted,
                'revision' => $output->revision,
            ], JSON_THROW_ON_ERROR));

            return $output;
        });
        foreach ($removed as $photo) {
            $this->files->remove($photo['publicId'], $photo['originalPath']);
        }

        return $output;
    }

    private function restore(string $json): DeletionMutationOutputDto
    {
        $value = json_decode($json, true, 4, JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new \UnexpectedValueException('Invalid stored deletion result.');
        }

        return new DeletionMutationOutputDto((int)$value['deleted'], (int)$value['revision']);
    }
}
