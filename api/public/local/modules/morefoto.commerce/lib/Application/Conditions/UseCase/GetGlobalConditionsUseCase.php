<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\UseCase;

use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Conditions\Dto\ConditionsOutputDto;
use Morefoto\Commerce\Application\Conditions\Service\ConditionsProducts;
use Morefoto\Commerce\Application\Conditions\Service\PublishedPrices;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;
use Morefoto\Commerce\Domain\Conditions\Repository\SalesConditionsRepository;

/**
 * Читает общие условия продажи: базовые цены и доступность каталога, подарок и политику учёта расходов на оплату
 * вместе с ценами для покупателя, которые из неё следуют.
 */
final readonly class GetGlobalConditionsUseCase
{
    public function __construct(
        private SalesConditionsRepository $conditions,
        private CatalogRepository $catalogue,
        private CatalogTransactionInterface $transaction,
        private ConditionsProducts $products,
        private PublishedPrices $prices,
    ) {}

    public function execute(): ConditionsOutputDto
    {
        return $this->transaction->execute(fn(): ConditionsOutputDto => $this->executeWithinTransaction());
    }

    public function executeWithinTransaction(): ConditionsOutputDto
    {
        $state = $this->conditions->global(false);
        $catalogRevision = $this->catalogue->lockRevision(false);
        $revision = (int)$state['REVISION'];
        $products = $this->products->read($this->conditions->globalProducts());
        $policy = $this->prices->policy($state);

        return new ConditionsOutputDto(
            revision: $revision,
            catalogRevision: $catalogRevision,
            conditionsRevision: $revision,
            inherit: false,
            products: $products,
            giftThreshold: (int)$state['GIFT_THRESHOLD'],
            giftForStaff: 1 === (int)$state['GIFT_FOR_STAFF'],
            paymentCosts: $this->prices->output($policy),
            salePrices: $this->prices->salePrices($products, $policy),
        );
    }
}
