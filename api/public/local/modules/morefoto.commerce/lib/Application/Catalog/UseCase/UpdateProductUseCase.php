<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\UseCase;

use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Catalog\Dto\CatalogMutationOutputDto;
use Morefoto\Commerce\Application\Catalog\Dto\ProductOutputDto;
use Morefoto\Commerce\Application\Catalog\Dto\UpdateProductInputDto;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogRevisionConflictException;
use Morefoto\Commerce\Domain\Catalog\Exception\ProductNotFoundException;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;

final readonly class UpdateProductUseCase
{
    public function __construct(private CatalogRepository $repository, private CatalogTransactionInterface $transaction) {}

    public function execute(UpdateProductInputDto $input): CatalogMutationOutputDto
    {
        return $this->transaction->execute(fn(): CatalogMutationOutputDto => $this->executeWithinTransaction($input));
    }

    /** Caller owns the transaction; this participant never commits or rolls back. */
    public function executeWithinTransaction(UpdateProductInputDto $input): CatalogMutationOutputDto
    {
        $revision = $this->repository->lockRevision(true);
        if ($input->revision !== $revision) {
            throw new CatalogRevisionConflictException('Catalog changed; reload before updating.');
        }
        $row = $this->repository->find($input->id)->fetch();
        if (false === $row) {
            throw new ProductNotFoundException('Product not found.');
        }
        $this->repository->update($input->id, $input->apply(ProductOutputDto::fromRow($row)));

        return new CatalogMutationOutputDto($input->id->value, $this->repository->advanceRevision($revision));
    }
}
