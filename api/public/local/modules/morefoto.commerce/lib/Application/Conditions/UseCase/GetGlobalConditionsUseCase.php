<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\UseCase;

use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Conditions\Dto\ConditionsOutputDto;
use Morefoto\Commerce\Application\Conditions\Service\ConditionsProducts;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;
use Morefoto\Commerce\Domain\Conditions\Repository\SalesConditionsRepository;

final readonly class GetGlobalConditionsUseCase
{
    public function __construct(private SalesConditionsRepository $conditions, private CatalogRepository $catalogue, private CatalogTransactionInterface $transaction, private ConditionsProducts $products) {}

    public function execute(): ConditionsOutputDto
    {
        return $this->transaction->execute(fn(): ConditionsOutputDto => $this->executeWithinTransaction());
    }

    public function executeWithinTransaction(): ConditionsOutputDto
    {
        $state = $this->conditions->global(false);
        $catalogRevision = $this->catalogue->lockRevision(false);
        $revision = (int)$state['REVISION'];

        return new ConditionsOutputDto(
            revision: $revision,
            catalogRevision: $catalogRevision,
            conditionsRevision: $revision,
            inherit: false,
            products: $this->products->read($this->conditions->globalProducts()),
            giftThreshold: (int)$state['GIFT_THRESHOLD'],
            giftForStaff: 1 === (int)$state['GIFT_FOR_STAFF'],
        );
    }
}
