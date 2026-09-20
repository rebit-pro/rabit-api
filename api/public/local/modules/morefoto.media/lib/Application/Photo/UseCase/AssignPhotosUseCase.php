<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\UseCase;

use Morefoto\Media\Application\Photo\Contract\MediaTransactionInterface;
use Morefoto\Media\Application\Photo\Dto\AssignmentMutationOutputDto;
use Morefoto\Media\Application\Photo\Dto\AssignPhotosInputDto;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Photo\ValueObject\IdempotencyKey;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class AssignPhotosUseCase
{
    public function __construct(
        private MediaTransactionInterface $transaction,
        private MediaMutationRepository $media,
        private MediaScopeInterface $scopes,
        private AccessGuardInterface $access,
    ) {}

    public function execute(int $actorId, string $groupId, IdempotencyKey $key, AssignPhotosInputDto $input): AssignmentMutationOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $groupId, $key, $input): AssignmentMutationOutputDto {
            $scope = $this->scopes->resolve($input->shootId, $groupId);
            $this->access->assertCan($actorId, 'media.manage', $scope->institutionId, $scope->groupId);
            if (!$scope->groupEditable || null === $scope->groupId) {
                throw new HttpException('GROUP_LOCKED', 409);
            }
            $current = $this->media->lockRevision($scope->shootId);
            $resource = '/groups/' . $groupId . '/photo-assignments';
            $hash = hash('sha256', json_encode([
                'shootId' => $input->shootId,
                'revision' => $input->revision,
                'photoIds' => $input->photoIds,
                'childCode' => $input->childCode,
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
            $photos = $this->media->readyPhotos($scope->shootId, $scope->groupId, $input->photoIds);
            $child = $this->media->child($scope->shootId, $scope->groupId, $input->childCode);
            $nativeIds = array_map(static fn(string $photoId): int => $photos[$photoId], $input->photoIds);
            $changed = $this->media->assign($child['id'], $nativeIds);
            $revision = $changed ? $this->media->advanceRevision($scope->shootId, $current) : $current;
            $output = new AssignmentMutationOutputDto($input->photoIds, $input->childCode, $child['publicId'], $revision);
            $this->media->saveIdempotency($actorId, $resource, $key, $hash, json_encode([
                'photoIds' => $output->photoIds,
                'childCode' => $output->childCode,
                'childId' => $output->childId,
                'revision' => $output->revision,
            ], JSON_THROW_ON_ERROR));

            return $output;
        });
    }

    private function restore(string $json): AssignmentMutationOutputDto
    {
        $value = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($value) || !is_array($value['photoIds'] ?? null)) {
            throw new \UnexpectedValueException('Invalid stored assignment result.');
        }

        return new AssignmentMutationOutputDto(
            array_values(array_map('strval', $value['photoIds'])),
            (string)$value['childCode'],
            (string)$value['childId'],
            (int)$value['revision'],
        );
    }
}
