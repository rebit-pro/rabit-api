<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\Service;

use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Conditions\Dto\ConditionsMutationOutputDto;
use Morefoto\Commerce\Application\Conditions\Dto\ConditionsOutputDto;
use Morefoto\Commerce\Application\Conditions\Dto\SaveConditionsInputDto;
use Morefoto\Commerce\Application\Conditions\UseCase\GetGlobalConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\GetGroupConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\SaveGlobalConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\SaveGroupConditionsUseCase;
use Morefoto\Commerce\Domain\Catalog\Exception\IdempotencyConflictException;
use Morefoto\Commerce\Domain\Catalog\ValueObject\IdempotencyKey;
use Morefoto\Commerce\Domain\Conditions\Repository\ConditionsIdempotencyRepository;
use Rebit\Share\Contracts\Access\CatalogAccessGuardInterface;
use Rebit\Share\Contracts\Organization\GroupReferenceInterface;

final readonly class AuthorizedConditions
{
    public function __construct(
        private CatalogTransactionInterface $transaction,
        private CatalogAccessGuardInterface $access,
        private GroupReferenceInterface $groups,
        private ConditionsIdempotencyRepository $keys,
        private ConditionsPayloadHash $hashes,
        private GetGlobalConditionsUseCase $getGlobal,
        private SaveGlobalConditionsUseCase $saveGlobal,
        private GetGroupConditionsUseCase $getGroup,
        private SaveGroupConditionsUseCase $saveGroup,
    ) {}

    public function getGlobal(int $actorId, string $token): ConditionsOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $token): ConditionsOutputDto {
            $this->access->lockOrganizer($actorId, $token);

            return $this->getGlobal->executeWithinTransaction();
        });
    }

    public function getGroup(int $actorId, string $token, string $groupId): ConditionsOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $token, $groupId): ConditionsOutputDto {
            $this->access->lockOrganizer($actorId, $token);
            $group = $this->groups->get($groupId);

            return $this->getGroup->executeWithinTransaction($group->nativeId);
        });
    }

    public function saveGlobal(int $actorId, string $token, IdempotencyKey $key, SaveConditionsInputDto $input): ConditionsMutationOutputDto
    {
        return $this->mutate($actorId, $token, '/catalog/conditions', $key, $input, fn(): ConditionsMutationOutputDto => $this->saveGlobal->executeWithinTransaction($input));
    }

    public function saveGroup(int $actorId, string $token, string $groupId, IdempotencyKey $key, SaveConditionsInputDto $input): ConditionsMutationOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $token, $groupId, $key, $input): ConditionsMutationOutputDto {
            $this->access->lockOrganizer($actorId, $token);
            $group = $this->groups->get($groupId);

            return $this->mutateWithinTransaction(
                actorId: $actorId,
                resource: '/groups/' . $group->id . '/conditions',
                key: $key,
                input: $input,
                operation: fn(): ConditionsMutationOutputDto => $this->saveGroup->executeWithinTransaction($group->nativeId, $input),
            );
        });
    }

    /** @param callable(): ConditionsMutationOutputDto $operation */
    private function mutate(int $actorId, string $token, string $resource, IdempotencyKey $key, SaveConditionsInputDto $input, callable $operation): ConditionsMutationOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $token, $resource, $key, $input, $operation): ConditionsMutationOutputDto {
            $this->access->lockOrganizer($actorId, $token);

            return $this->mutateWithinTransaction($actorId, $resource, $key, $input, $operation);
        });
    }

    /** @param callable(): ConditionsMutationOutputDto $operation */
    private function mutateWithinTransaction(int $actorId, string $resource, IdempotencyKey $key, SaveConditionsInputDto $input, callable $operation): ConditionsMutationOutputDto
    {
        $hash = $this->hashes->create($input);
        $row = $this->keys->find($actorId, $resource, $key)->fetch();
        if (false !== $row) {
            if (!hash_equals((string)$row['PAYLOAD_HASH'], $hash)) {
                throw new IdempotencyConflictException('Idempotency key was already used with a different request.');
            }

            return new ConditionsMutationOutputDto((int)$row['RESULT_REVISION'], (int)$row['RESULT_CATALOG_REVISION'], (int)$row['RESULT_CONDITIONS_REVISION']);
        }
        $result = $operation();
        $this->keys->save($actorId, $resource, $key, $hash, $result);

        return $result;
    }
}
