<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\UseCase;

use Morefoto\Media\Application\Photo\Contract\MediaTransactionInterface;
use Morefoto\Media\Application\Photo\Dto\CoverMutationOutputDto;
use Morefoto\Media\Application\Photo\Dto\SetCoverInputDto;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Photo\ValueObject\IdempotencyKey;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\GroupReferenceInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Атомарно назначает готовую фотографию обложкой редактируемой группы.
 *
 * Проверяет доступ, принадлежность фотографии, revision и идемпотентность, затем обновляет media revision.
 */
final readonly class SetGroupCoverUseCase
{
    public function __construct(
        private MediaTransactionInterface $transaction,
        private MediaMutationRepository $media,
        private GroupReferenceInterface $groups,
        private MediaScopeInterface $scopes,
        private AccessGuardInterface $access,
    ) {}

    public function execute(int $actorId, string $groupId, IdempotencyKey $key, SetCoverInputDto $input): CoverMutationOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $groupId, $key, $input): CoverMutationOutputDto {
            $group = $this->groups->get($groupId);
            $scope = $this->scopes->resolve($group->shootId, $groupId);
            $this->access->assertCan($actorId, 'media.manage', $scope->institutionId, $scope->groupId);
            if (!$scope->groupEditable || null === $scope->groupId) {
                throw new HttpException('GROUP_LOCKED', 409);
            }
            $current = $this->media->lockRevision($scope->shootId);
            // Link delivery takes the same lock: re-read after waiting so the cover cannot change in an opened gallery.
            if (!$this->scopes->resolve($group->shootId, $groupId)->groupEditable) {
                throw new HttpException('GROUP_LOCKED', 409);
            }
            $resource = '/groups/' . $groupId . '/cover';
            $hash = hash('sha256', json_encode([
                'revision' => $input->revision,
                'photoId' => $input->photoId,
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
            $photoId = $this->media->assertCoverPhoto($scope->shootId, $scope->groupId, $input->photoId);
            $changed = $this->media->setCover($scope->groupId, $photoId);
            $revision = $changed ? $this->media->advanceRevision($scope->shootId, $current) : $current;
            $output = new CoverMutationOutputDto($input->photoId, $revision);
            $this->media->saveIdempotency($actorId, $resource, $key, $hash, json_encode([
                'photoId' => $output->photoId,
                'revision' => $output->revision,
            ], JSON_THROW_ON_ERROR));

            return $output;
        });
    }

    private function restore(string $json): CoverMutationOutputDto
    {
        $value = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new \UnexpectedValueException('Invalid stored cover result.');
        }

        return new CoverMutationOutputDto((string)$value['photoId'], (int)$value['revision']);
    }
}
