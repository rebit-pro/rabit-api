<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\Service;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Application\Catalog\Dto\ProductOutputDto;
use Morefoto\Commerce\Application\Conditions\Dto\ProductConditionInputDto;
use Morefoto\Commerce\Application\Conditions\Dto\SaveConditionsInputDto;
use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;

final readonly class ConditionsProducts
{
    /** @return list<ProductOutputDto> */
    public function read(Result $result): array
    {
        $products = [];
        while (false !== ($row = $result->fetch())) {
            $products[] = ProductOutputDto::fromRow($row);
        }

        return $products;
    }

    /**
     * @param list<ProductOutputDto> $catalogue
     *
     * @return list<ProductConditionInputDto>
     */
    public function validate(SaveConditionsInputDto $input, array $catalogue, bool $group): array
    {
        if (count($input->products) !== count($catalogue)) {
            throw new InvalidConditionsException('The complete current catalogue is required.');
        }
        $submitted = [];
        foreach ($input->products as $product) {
            $submitted[$product->id->value] = $product;
        }
        $ordered = [];
        $activeBundles = 0;
        foreach ($catalogue as $base) {
            $product = $submitted[$base->id] ?? null;
            if (!$product instanceof ProductConditionInputDto) {
                throw new InvalidConditionsException('The condition product set does not match the catalogue.');
            }
            if ($group && $product->active && !$base->active) {
                throw new InvalidConditionsException('A group cannot enable a product disabled in the global catalogue.');
            }
            if (ProductKind::BUNDLE === $base->kind && $product->active) {
                ++$activeBundles;
            }
            $ordered[] = $product;
        }
        if ($input->giftEnabled && 1 !== $activeBundles) {
            throw new InvalidConditionsException('An enabled gift requires exactly one active bundle product.');
        }

        return $ordered;
    }
}
