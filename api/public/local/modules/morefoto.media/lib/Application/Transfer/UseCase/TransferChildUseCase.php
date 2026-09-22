<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Transfer\UseCase;

use Morefoto\Media\Application\Photo\Contract\MediaTransactionInterface;
use Morefoto\Media\Application\Transfer\Dto\TransferChildInputDto;
use Morefoto\Media\Application\Transfer\Dto\TransferChildOutputDto;
use Morefoto\Media\Application\Transfer\Service\ChildTransfers;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Photo\ValueObject\IdempotencyKey;
use Morefoto\Media\Domain\Transfer\Repository\ChildTransferRepository;
use Morefoto\Media\Domain\Transfer\Service\ChildTransferPolicy;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Commerce\ChildOrdersInterface;
use Rebit\Share\Contracts\Media\Dto\ChildMoveInputDto;
use Rebit\Share\Contracts\Media\Dto\ChildSetPhotoOutputDto;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Исправляет разметку до открытия галереи: переносит полный набор ребёнка в другую группу того же типа одной съёмки.
 * Проверяет права организатора, ревизию медиа, точный набор, свободный код, совместные кадры и отсутствие заказов;
 * повтор с тем же ключом возвращает сохранённый результат без второго переноса.
 */
final readonly class TransferChildUseCase
{
    public function __construct(
        private MediaTransactionInterface $transaction,
        private MediaMutationRepository $media,
        private ChildTransferRepository $transfers,
        private ChildTransfers $children,
        private ChildTransferPolicy $policy,
        private MediaScopeInterface $scopes,
        private AccessGuardInterface $access,
        private ChildOrdersInterface $orders,
    ) {}

    public function execute(int $actorId, IdempotencyKey $key, TransferChildInputDto $input): TransferChildOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $key, $input): TransferChildOutputDto {
            $from = $this->scopes->resolve($input->shootId, $input->fromGroupId);
            $to = $this->scopes->resolve($input->shootId, $input->toGroupId);
            $this->authorize($actorId, $from);
            $this->authorize($actorId, $to);
            $shootId = $from->shootId;
            $current = $this->media->lockRevision($shootId);
            $resource = '/shoots/' . $input->shootId . '/child-transfers';
            $hash = hash('sha256', json_encode([
                'fromGroupId' => $input->fromGroupId,
                'toGroupId' => $input->toGroupId,
                'childCode' => $input->childCode,
                'targetCode' => $input->targetCode,
                'expectedPhotoIds' => $input->expectedPhotoIds,
                'revision' => $input->revision,
            ], JSON_THROW_ON_ERROR));
            $stored = $this->media->idempotency($actorId, $resource, $key)->fetch();
            if (false !== $stored) {
                if (!hash_equals((string)$stored['PAYLOAD_HASH'], $hash)) {
                    throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
                }

                return $this->restore((string)$stored['RESULT_JSON']);
            }
            // Link delivery takes the same media lock: the group state is read after waiting for it.
            $from = $this->scopes->resolve($input->shootId, $input->fromGroupId);
            $to = $this->scopes->resolve($input->shootId, $input->toGroupId);
            if (null === $from->groupId || null === $to->groupId) {
                throw new HttpException('GROUP_NOT_FOUND', 404);
            }
            if ($from->groupKind !== $to->groupKind) {
                throw new HttpException('GROUP_KIND_MISMATCH', 409);
            }
            if (!$from->groupEditable || !$to->groupEditable) {
                throw new HttpException('GROUP_LOCKED', 409);
            }
            if ($input->revision !== $current) {
                throw new HttpException('REVISION_CONFLICT', 409);
            }
            $childId = $this->transfers->childId($shootId, $from->groupId, $input->childCode)
                ?? throw new HttpException('SET_CHANGED', 409);
            $set = $this->children->lockSets($shootId, [$childId])[$childId] ?? throw new HttpException('SET_CHANGED', 409);
            $photoIds = array_map(static fn(ChildSetPhotoOutputDto $photo): string => $photo->id, $set->photos);
            if ([] === $photoIds || !$this->policy->sameSet($input->expectedPhotoIds, $photoIds)) {
                throw new HttpException('SET_CHANGED', 409);
            }
            if ([] !== $set->sharedPhotoCodes) {
                throw new HttpException('SHARED_PHOTO', 409, null, ['photoCodes' => $set->sharedPhotoCodes]);
            }
            if (in_array($input->targetCode, $this->transfers->codes($to->groupId), true)) {
                throw new HttpException('TARGET_CODE_TAKEN', 409);
            }
            if ([] !== $this->orders->withOrders([$childId])) {
                throw new HttpException('CHILD_HAS_ORDERS', 409);
            }
            $revision = $this->children->move($shootId, [new ChildMoveInputDto($childId, $to->groupId, $input->targetCode)]);
            $output = new TransferChildOutputDto($photoIds, $input->fromGroupId, $input->toGroupId, $input->targetCode, $revision);
            $this->media->saveIdempotency($actorId, $resource, $key, $hash, json_encode([
                'photoIds' => $output->photoIds,
                'fromGroupId' => $output->fromGroupId,
                'toGroupId' => $output->toGroupId,
                'childCode' => $output->childCode,
                'revision' => $output->revision,
            ], JSON_THROW_ON_ERROR));

            return $output;
        });
    }

    private function authorize(int $actorId, MediaScopeOutputDto $scope): void
    {
        try {
            $this->access->assertCan($actorId, 'media.manage', $scope->institutionId, $scope->groupId);
        } catch (HttpException $error) {
            // Access reports refusals as text until #42; the transfer contract promises error codes.
            throw match ($error->getCode()) {
                401 => new HttpException('UNAUTHORIZED', 401, $error),
                403 => new HttpException('FORBIDDEN', 403, $error),
                404 => new HttpException('GROUP_NOT_FOUND', 404, $error),
                default => $error,
            };
        }
    }

    private function restore(string $json): TransferChildOutputDto
    {
        $value = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($value) || !is_array($value['photoIds'] ?? null)) {
            throw new \UnexpectedValueException('Invalid stored child transfer result.');
        }

        return new TransferChildOutputDto(
            array_values(array_map('strval', $value['photoIds'])),
            (string)$value['fromGroupId'],
            (string)$value['toGroupId'],
            (string)$value['childCode'],
            (int)$value['revision'],
        );
    }
}
