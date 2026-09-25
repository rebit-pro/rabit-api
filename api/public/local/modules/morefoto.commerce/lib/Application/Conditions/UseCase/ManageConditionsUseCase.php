<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\UseCase;

use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Conditions\Dto\ConditionsMutationOutputDto;
use Morefoto\Commerce\Application\Conditions\Dto\ConditionsOutputDto;
use Morefoto\Commerce\Application\Conditions\Dto\SaveConditionsInputDto;
use Morefoto\Commerce\Application\Conditions\Service\ConditionsPayloadHash;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogRevisionConflictException;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogStorageException;
use Morefoto\Commerce\Domain\Catalog\Exception\IdempotencyConflictException;
use Morefoto\Commerce\Domain\Catalog\Exception\InvalidProductException;
use Morefoto\Commerce\Domain\Catalog\ValueObject\IdempotencyKey;
use Morefoto\Commerce\Domain\Conditions\Exception\ConditionsRevisionConflictException;
use Morefoto\Commerce\Domain\Conditions\Exception\ConditionsStorageException;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;
use Morefoto\Commerce\Domain\Conditions\Repository\ConditionsIdempotencyRepository;
use Rebit\Share\Contracts\Access\CatalogAccessException;
use Rebit\Share\Contracts\Access\CatalogAccessGuardInterface;
use Rebit\Share\Contracts\Organization\GroupReferenceInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Управление условиями продажи организатором: чтение и сохранение общих условий и условий группы под проверкой
 * права каталога и сессии, идемпотентный повтор сохранения. Предметные отказы переводятся в коды API условий.
 */
final readonly class ManageConditionsUseCase
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
        return $this->translate(fn(): ConditionsOutputDto => $this->transaction->execute(function() use ($actorId, $token): ConditionsOutputDto {
            $this->access->lockOrganizer($actorId, $token);

            return $this->getGlobal->executeWithinTransaction();
        }));
    }

    public function getGroup(int $actorId, string $token, string $groupId): ConditionsOutputDto
    {
        return $this->translate(fn(): ConditionsOutputDto => $this->transaction->execute(function() use ($actorId, $token, $groupId): ConditionsOutputDto {
            $this->access->lockOrganizer($actorId, $token);
            $group = $this->groups->get($groupId);

            return $this->getGroup->executeWithinTransaction($group->nativeId);
        }));
    }

    public function saveGlobal(int $actorId, string $token, string $idempotencyKey, SaveConditionsInputDto $input): ConditionsMutationOutputDto
    {
        return $this->translate(function() use ($actorId, $token, $idempotencyKey, $input): ConditionsMutationOutputDto {
            $key = new IdempotencyKey($idempotencyKey);

            return $this->transaction->execute(function() use ($actorId, $token, $key, $input): ConditionsMutationOutputDto {
                $this->access->lockOrganizer($actorId, $token);

                return $this->mutateWithinTransaction($actorId, '/catalog/conditions', $key, $input, fn(): ConditionsMutationOutputDto => $this->saveGlobal->executeWithinTransaction($input));
            });
        });
    }

    public function saveGroup(int $actorId, string $token, string $groupId, string $idempotencyKey, SaveConditionsInputDto $input): ConditionsMutationOutputDto
    {
        return $this->translate(function() use ($actorId, $token, $groupId, $idempotencyKey, $input): ConditionsMutationOutputDto {
            $key = new IdempotencyKey($idempotencyKey);

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

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    private function translate(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (InvalidConditionsException|InvalidProductException $exception) {
            throw new HttpException('VALIDATION_FAILED', 422, $exception);
        } catch (CatalogRevisionConflictException|ConditionsRevisionConflictException $exception) {
            throw new HttpException('REVISION_CONFLICT', 409, $exception);
        } catch (IdempotencyConflictException $exception) {
            throw new HttpException('IDEMPOTENCY_CONFLICT', 409, $exception);
        } catch (CatalogStorageException|ConditionsStorageException $exception) {
            throw new HttpException('CONDITIONS_UNAVAILABLE', 503, $exception);
        } catch (CatalogAccessException $exception) {
            throw match ($exception->getCode()) {
                401 => new HttpException('UNAUTHORIZED', 401, $exception),
                403 => new HttpException('FORBIDDEN', 403, $exception),
                default => new HttpException('ACCESS_UNAVAILABLE', 503, $exception),
            };
        }
    }
}
