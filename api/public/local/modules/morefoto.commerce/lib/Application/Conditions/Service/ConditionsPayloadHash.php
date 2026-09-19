<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\Service;

use Morefoto\Commerce\Application\Conditions\Dto\SaveConditionsInputDto;

final readonly class ConditionsPayloadHash
{
    public function create(SaveConditionsInputDto $input): string
    {
        $products = [];
        foreach ($input->products as $product) {
            $products[] = [$product->id->value, $product->price, $product->active, $product->staffDiscount];
        }
        usort($products, static fn(array $left, array $right): int => $left[0] <=> $right[0]);

        return hash('sha256', json_encode([
            'revision' => $input->revision,
            'catalogRevision' => $input->catalogRevision,
            'conditionsRevision' => $input->conditionsRevision,
            'inherit' => $input->inherit,
            'products' => $products,
            'giftEnabled' => $input->giftEnabled,
            'giftThreshold' => $input->giftThreshold,
            'giftForStaff' => $input->giftForStaff,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
