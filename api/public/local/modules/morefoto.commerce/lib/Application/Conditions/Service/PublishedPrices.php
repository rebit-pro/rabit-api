<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\Service;

use Morefoto\Commerce\Application\Catalog\Dto\ProductOutputDto;
use Morefoto\Commerce\Application\Conditions\Dto\ConditionsOutputDto;
use Morefoto\Commerce\Application\Conditions\Dto\PaymentCostsOutputDto;
use Morefoto\Commerce\Domain\Conditions\Exception\ConditionsStorageException;
use Morefoto\Commerce\Domain\Conditions\ValueObject\PaymentCostPolicy;

/**
 * Публикует цену для покупателя по политике учёта расходов на оплату (E6): одна цена единицы каталога
 * для витрины, расчёта корзины и снимка заказа при неизменных базовых ценах условий.
 */
final readonly class PublishedPrices
{
    /** @param array{PAYMENT_COSTS_ENABLED: int|string, PAYMENT_COST_RATE_BPS: int|string} $state строка общих условий */
    public function policy(array $state): PaymentCostPolicy
    {
        return new PaymentCostPolicy(1 === (int)$state['PAYMENT_COSTS_ENABLED'], (int)$state['PAYMENT_COST_RATE_BPS']);
    }

    public function output(PaymentCostPolicy $policy): PaymentCostsOutputDto
    {
        return new PaymentCostsOutputDto($policy->enabled, $policy->rateBps, PaymentCostPolicy::ROUNDING_STEP, PaymentCostPolicy::MAX_RATE_BPS);
    }

    /**
     * @param list<ProductOutputDto> $products
     *
     * @return array<string, int>
     */
    public function salePrices(array $products, PaymentCostPolicy $policy): array
    {
        $prices = [];
        foreach ($products as $product) {
            $prices[$product->id] = $policy->salePrice($product->price);
        }

        return $prices;
    }

    /** @return list<ProductOutputDto> товары условий с ценой для покупателя вместо базовой */
    public function publish(ConditionsOutputDto $conditions): array
    {
        $products = [];
        foreach ($conditions->products as $product) {
            $products[] = new ProductOutputDto(
                id: $product->id,
                name: $product->name,
                description: $product->description,
                kind: $product->kind,
                price: $conditions->salePrices[$product->id] ?? throw new ConditionsStorageException('Sale price is missing for a condition product.'),
                printCount: $product->printCount,
                format: $product->format,
                unit: $product->unit,
                staffDiscount: $product->staffDiscount,
                active: $product->active,
            );
        }

        return $products;
    }
}
