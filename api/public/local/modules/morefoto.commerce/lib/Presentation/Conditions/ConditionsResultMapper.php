<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Conditions;

use Morefoto\Commerce\Application\Conditions\Dto\ConditionsMutationOutputDto;
use Morefoto\Commerce\Application\Conditions\Dto\ConditionsOutputDto;
use Morefoto\Commerce\Presentation\Conditions\Dto\ConditionsResultDto;
use Morefoto\Commerce\Presentation\Conditions\Dto\ConditionsSavedResultDto;

/** Формирует ответы API условий: базовая цена для правки, цена для покупателя и политика учёта расходов. */
final readonly class ConditionsResultMapper
{
    public function global(ConditionsOutputDto $output): ConditionsResultDto
    {
        return new ConditionsResultDto(...$this->common($output));
    }

    public function group(ConditionsOutputDto $output): ConditionsResultDto
    {
        return new ConditionsResultDto(...$this->common($output), conditionsRevision: $output->conditionsRevision, inherit: $output->inherit);
    }

    public function savedGlobal(ConditionsMutationOutputDto $output): ConditionsSavedResultDto
    {
        return new ConditionsSavedResultDto($output->revision);
    }

    public function savedGroup(ConditionsMutationOutputDto $output): ConditionsSavedResultDto
    {
        return new ConditionsSavedResultDto($output->revision, $output->conditionsRevision);
    }

    /** @return array{
     *     revision: int,
     *     catalogRevision: int,
     *     products: list<array{id: string, name: string, description: string, kind: string, price: int, salePrice: int, printCount: int, format: string, unit: string, staffDiscount: bool, active: bool}>,
     *     giftThreshold: int,
     *     giftForStaff: bool,
     *     paymentCosts: array{enabled: bool, rateBps: int, roundingStep: int, maxRateBps: int},
     * } */
    private function common(ConditionsOutputDto $output): array
    {
        $products = [];
        foreach ($output->products as $product) {
            $products[] = [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'kind' => $product->kind->value,
                'price' => $product->price,
                'salePrice' => $output->salePrices[$product->id] ?? $product->price,
                'printCount' => $product->printCount,
                'format' => $product->format,
                'unit' => $product->unit,
                'staffDiscount' => $product->staffDiscount,
                'active' => $product->active,
            ];
        }

        return [
            'revision' => $output->revision,
            'catalogRevision' => $output->catalogRevision,
            'products' => $products,
            'giftThreshold' => $output->giftThreshold,
            'giftForStaff' => $output->giftForStaff,
            'paymentCosts' => [
                'enabled' => $output->paymentCosts->enabled,
                'rateBps' => $output->paymentCosts->rateBps,
                'roundingStep' => $output->paymentCosts->roundingStep,
                'maxRateBps' => $output->paymentCosts->maxRateBps,
            ],
        ];
    }
}
