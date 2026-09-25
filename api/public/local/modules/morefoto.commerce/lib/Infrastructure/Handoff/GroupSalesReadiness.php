<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Handoff;

use Morefoto\Commerce\Application\Conditions\Service\ConditionsProducts;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;
use Morefoto\Commerce\Domain\Conditions\Repository\SalesConditionsRepository;
use Rebit\Share\Contracts\Commerce\Dto\SalesReadinessOutputDto;
use Rebit\Share\Contracts\Commerce\GroupSalesReadinessInterface;

final readonly class GroupSalesReadiness implements GroupSalesReadinessInterface
{
    public function __construct(
        private SalesConditionsRepository $conditions,
        private CatalogRepository $catalogue,
        private ConditionsProducts $products,
    ) {}

    public function readiness(array $groupIds): array
    {
        $ids = array_values(array_unique(array_filter($groupIds, static fn(int $id): bool => 0 < $id)));
        if ([] === $ids) {
            return [];
        }
        // Same share locks and effective products as GetGroupConditionsUseCase, so reads and commands fingerprint identically.
        $global = $this->conditions->global(false);
        $catalogRevision = $this->catalogue->lockRevision(false);
        $groups = $this->conditions->groups($ids);
        $globalProducts = null;
        $result = [];
        foreach ($ids as $id) {
            $group = $groups[$id] ?? null;
            $inherit = null === $group || 1 === (int)$group['INHERIT'];
            $products = $inherit
                ? $globalProducts ??= $this->products->read($this->conditions->globalProducts())
                : $this->products->read($this->conditions->groupProducts($id));
            $active = 0;
            $effective = [];
            foreach ($products as $product) {
                if ($product->active && 0 <= $product->price) {
                    ++$active;
                }
                $effective[] = [$product->id, $product->kind->value, $product->price, $product->staffDiscount, $product->active];
            }
            $result[$id] = new SalesReadinessOutputDto($active, hash('sha256', json_encode([
                $catalogRevision,
                (int)$global['REVISION'],
                null === $group ? 0 : (int)$group['REVISION'],
                $inherit,
                (int)(null === $group || $inherit ? $global['GIFT_THRESHOLD'] : $group['GIFT_THRESHOLD']),
                (int)(null === $group || $inherit ? $global['GIFT_FOR_STAFF'] : $group['GIFT_FOR_STAFF']),
                $effective,
            ], JSON_THROW_ON_ERROR)));
        }

        return $result;
    }
}
