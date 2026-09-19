<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Mapper;

use Morefoto\Commerce\Application\Conditions\Dto\ConditionsOutputDto;

final readonly class ConditionsResponseMapper
{
    /** @return array<string, mixed> */
    public function data(ConditionsOutputDto $output, bool $group): array
    {
        $products = [];
        foreach ($output->products as $product) {
            $products[] = [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'kind' => $product->kind->value,
                'price' => $product->price,
                'printCount' => $product->printCount,
                'format' => $product->format,
                'unit' => $product->unit,
                'staffDiscount' => $product->staffDiscount,
                'active' => $product->active,
            ];
        }
        $result = [
            'revision' => $output->revision,
            'catalogRevision' => $output->catalogRevision,
            'products' => $products,
            'giftThreshold' => $output->giftThreshold,
            'giftForStaff' => $output->giftForStaff,
        ];
        if ($group) {
            $result['conditionsRevision'] = $output->conditionsRevision;
            $result['inherit'] = $output->inherit;
        }

        return $result;
    }
}
