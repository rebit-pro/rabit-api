<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\UseCase;

use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Conditions\Dto\ConditionsOutputDto;
use Morefoto\Commerce\Application\Conditions\Service\ConditionsProducts;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;
use Morefoto\Commerce\Domain\Conditions\Repository\SalesConditionsRepository;

final readonly class GetGroupConditionsUseCase
{
    public function __construct(private SalesConditionsRepository $conditions, private CatalogRepository $catalogue, private CatalogTransactionInterface $transaction, private ConditionsProducts $products) {}

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
        $result = $inherit ? $this->conditions->globalProducts() : $this->conditions->groupProducts($groupId);

        return new ConditionsOutputDto(
            revision: $revision,
            catalogRevision: $catalogRevision,
            conditionsRevision: (int)$global['REVISION'],
            inherit: $inherit,
            products: $this->products->read($result),
            giftThreshold: $threshold,
            giftForStaff: $giftForStaff,
        );
    }
}
