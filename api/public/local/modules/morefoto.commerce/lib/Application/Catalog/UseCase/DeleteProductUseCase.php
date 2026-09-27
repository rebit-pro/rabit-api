<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\UseCase;

use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductId;
use Rebit\Share\Contracts\Access\CatalogAccessException;
use Rebit\Share\Contracts\Access\CatalogAccessGuardInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Убирает продукцию из каталога и из условий продажи групп, пока её никто не покупал. Купленную продукцию
 * организатор не удаляет, а снимает с продажи.
 */
final readonly class DeleteProductUseCase
{
    public function __construct(
        private CatalogRepository $repository,
        private CatalogAccessGuardInterface $access,
        private CatalogTransactionInterface $transaction,
    ) {}

    public function execute(int $actorId, string $token, ProductId $id): void
    {
        try {
            $this->transaction->execute(function() use ($actorId, $token, $id): void {
                $this->access->lockOrganizer($actorId, $token);
                $revision = $this->repository->lockRevision(true);
                if (false === $this->repository->find($id)->fetch()) {
                    throw new HttpException('NOT_FOUND', 404);
                }
                if ($this->repository->purchased($id)) {
                    throw new HttpException('PRODUCT_IN_ORDERS', 409);
                }
                $this->repository->delete($id);
                $this->repository->advanceRevision($revision);
            });
        } catch (CatalogAccessException $denied) {
            throw new HttpException(match ($denied->getCode()) {
                401 => 'UNAUTHORIZED',
                403 => 'FORBIDDEN',
                default => 'ACCESS_UNAVAILABLE',
            }, in_array($denied->getCode(), [401, 403], true) ? $denied->getCode() : 503, $denied);
        }
    }
}
