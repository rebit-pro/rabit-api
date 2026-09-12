<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\UseCase;

use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Catalog\Dto\ListProductsInputDto;
use Morefoto\Commerce\Application\Catalog\Dto\ListProductsOutputDto;
use Morefoto\Commerce\Application\Catalog\Dto\ProductOutputDto;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogRevisionConflictException;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;

final readonly class ListProductsUseCase
{
    public function __construct(private CatalogRepository $repository, private CatalogTransactionInterface $transaction) {}

    public function execute(ListProductsInputDto $input): ListProductsOutputDto
    {
        return $this->transaction->execute(fn(): ListProductsOutputDto => $this->executeWithinTransaction($input));
    }

    /** Caller owns the transaction; this participant never commits or rolls back. */
    public function executeWithinTransaction(ListProductsInputDto $input, bool $byName = false): ListProductsOutputDto
    {
        $revision = $this->repository->lockRevision(false);
        if (null !== $input->revision && $input->revision !== $revision) {
            throw new CatalogRevisionConflictException('Catalog changed; restart pagination.');
        }
        $total = $this->repository->count();
        $result = $this->repository->list(($input->page - 1) * $input->pageSize, $input->pageSize, $byName);
        $items = [];
        while (false !== ($row = $result->fetch())) {
            $items[] = ProductOutputDto::fromRow($row);
        }

        return new ListProductsOutputDto($items, $revision, $input->page, $input->pageSize, $total);
    }
}
