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
 * Собирает действующие условия группы: собственные или унаследованные от общих, с ценой для покупателя
 * по общей политике учёта расходов. Этот же результат использует витрина, расчёт корзины и заказ.
 */
final readonly class GetGroupConditionsUseCase
{
    public function __construct(
        private SalesConditionsRepository $conditions,
        private CatalogRepository $catalogue,
        private CatalogTransactionInterface $transaction,
        private ConditionsProducts $products,
        private PublishedPrices $prices,
    ) {}

    public function execute(int $groupId): ConditionsOutputDto
    {
        return $this->transaction->execute(fn(): ConditionsOutputDto => $this->executeWithinTransaction($groupId));
    }

    public function executeWithinTransaction(int $groupId): ConditionsOutputDto
    {
        $global = $this->conditions->global(false);
        $catalogRevision = $this->catalogue->lockRevision(false);
        $group = $this->conditions->group($groupId, false);
        $inherit = false === $group || 1 === (int)$group['INHERIT'];
        $revision = false === $group ? 0 : (int)$group['REVISION'];
        $threshold = $inherit ? (int)$global['GIFT_THRESHOLD'] : (int)$group['GIFT_THRESHOLD'];
        $giftForStaff = $inherit ? 1 === (int)$global['GIFT_FOR_STAFF'] : 1 === (int)$group['GIFT_FOR_STAFF'];
        $products = $this->products->read($inherit ? $this->conditions->globalProducts() : $this->conditions->groupProducts($groupId));
        $policy = $this->prices->policy($global);

        return new ConditionsOutputDto(
            revision: $revision,
            catalogRevision: $catalogRevision,
            conditionsRevision: (int)$global['REVISION'],
            inherit: $inherit,
            products: $products,
            giftThreshold: $threshold,
            giftForStaff: $giftForStaff,
            paymentCosts: $this->prices->output($policy),
            salePrices: $this->prices->salePrices($products, $policy),
        );
    }
}
