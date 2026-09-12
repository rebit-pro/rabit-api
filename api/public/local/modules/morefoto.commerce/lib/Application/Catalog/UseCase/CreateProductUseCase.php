<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\UseCase;

use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Catalog\Contract\ProductIdGeneratorInterface;
use Morefoto\Commerce\Application\Catalog\Dto\CatalogMutationOutputDto;
use Morefoto\Commerce\Application\Catalog\Dto\ProductInputDto;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;

final readonly class CreateProductUseCase
{
    public function __construct(
        private CatalogRepository $repository,
        private CatalogTransactionInterface $transaction,
        private ProductIdGeneratorInterface $ids,
    ) {}

    public function execute(ProductInputDto $input): CatalogMutationOutputDto
    {
        return $this->transaction->execute(fn(): CatalogMutationOutputDto => $this->executeWithinTransaction($input));
    }

    /** Caller owns the transaction; this participant never commits or rolls back. */
    public function executeWithinTransaction(ProductInputDto $input): CatalogMutationOutputDto
    {
        $revision = $this->repository->lockRevision(true);
        $id = $this->ids->generate();
        $this->repository->add($id, $input->details);

        return new CatalogMutationOutputDto($id->value, $this->repository->advanceRevision($revision));
    }
}
