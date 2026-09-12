<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\Service;

use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Catalog\Dto\CatalogMutationOutputDto;
use Morefoto\Commerce\Application\Catalog\Dto\ListProductsInputDto;
use Morefoto\Commerce\Application\Catalog\Dto\ListProductsOutputDto;
use Morefoto\Commerce\Application\Catalog\Dto\ProductInputDto;
use Morefoto\Commerce\Application\Catalog\Dto\UpdateProductInputDto;
use Morefoto\Commerce\Application\Catalog\UseCase\CreateProductUseCase;
use Morefoto\Commerce\Application\Catalog\UseCase\ListProductsUseCase;
use Morefoto\Commerce\Application\Catalog\UseCase\UpdateProductUseCase;
use Morefoto\Commerce\Domain\Catalog\Exception\IdempotencyConflictException;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogIdempotencyRepository;
use Morefoto\Commerce\Domain\Catalog\ValueObject\IdempotencyKey;
use Rebit\Share\Contracts\Access\CatalogAccessGuardInterface;

final readonly class AuthorizedCatalog
{
    public function __construct(
        private CatalogTransactionInterface $transaction,
        private CatalogAccessGuardInterface $access,
        private CatalogIdempotencyRepository $keys,
        private CatalogPayloadHash $hashes,
        private CreateProductUseCase $create,
        private UpdateProductUseCase $update,
        private ListProductsUseCase $list,
    ) {}

    public function create(int $actorId, string $token, IdempotencyKey $key, ProductInputDto $input): CatalogMutationOutputDto
    {
        return $this->mutate($actorId, $token, 'POST', '/catalog/products', $key, $this->hashes->create($input), fn(): CatalogMutationOutputDto => $this->create->executeWithinTransaction($input));
    }

    public function update(int $actorId, string $token, IdempotencyKey $key, UpdateProductInputDto $input): CatalogMutationOutputDto
    {
        return $this->mutate($actorId, $token, 'PATCH', '/catalog/products/' . $input->id->value, $key, $this->hashes->update($input), fn(): CatalogMutationOutputDto => $this->update->executeWithinTransaction($input));
    }

    public function list(int $actorId, string $token, ListProductsInputDto $input): ListProductsOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $token, $input): ListProductsOutputDto {
            $this->access->lockOrganizer($actorId, $token);

            return $this->list->executeWithinTransaction($input, true);
        });
    }

    /** @param callable(): CatalogMutationOutputDto $operation */
    private function mutate(int $actorId, string $token, string $method, string $resource, IdempotencyKey $key, string $hash, callable $operation): CatalogMutationOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $token, $method, $resource, $key, $hash, $operation): CatalogMutationOutputDto {
            $this->access->lockOrganizer($actorId, $token);
            /** @var array{
             *     PAYLOAD_HASH: string,
             *     PRODUCT_UUID: string,
             *     RESULT_REVISION: int|string,
             * }|false $row */
            $row = $this->keys->find($actorId, $method, $resource, $key)->fetch();
            if (false !== $row) {
                if (!hash_equals($row['PAYLOAD_HASH'], $hash)) {
                    throw new IdempotencyConflictException('Idempotency key was already used with a different request.');
                }

                return new CatalogMutationOutputDto($row['PRODUCT_UUID'], (int)$row['RESULT_REVISION']);
            }
            $result = $operation();
            $this->keys->save($actorId, $method, $resource, $key, $hash, $result->id, $result->revision);

            return $result;
        });
    }
}
