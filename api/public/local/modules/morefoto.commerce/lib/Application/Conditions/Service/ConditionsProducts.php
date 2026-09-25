<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\Service;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Application\Catalog\Dto\ProductOutputDto;
use Morefoto\Commerce\Application\Conditions\Dto\SaveConditionsInputDto;
use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;
use Morefoto\Commerce\Domain\Conditions\ValueObject\ConditionProduct;

/**
 * Читает эффективные товары условий и проверяет, что сохранение задаёт полный текущий каталог:
 * каждый товар ровно один раз, группа не включает отключённое в каталоге, подарок опирается на один комплект.
 */
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
     * @return list<ConditionProduct> в порядке каталога
     */
    public function validate(SaveConditionsInputDto $input, array $catalogue, bool $group): array
    {
        $submitted = [];
        foreach ($input->products as $value) {
            $product = new ConditionProduct($value->id, $value->price, $value->active, $value->staffDiscount);
            if (isset($submitted[$product->id->value])) {
                throw new InvalidConditionsException('Every product must occur exactly once.');
            }
            $submitted[$product->id->value] = $product;
        }
        if (count($submitted) !== count($catalogue)) {
            throw new InvalidConditionsException('The complete current catalogue is required.');
        }
        $ordered = [];
        $activeBundles = 0;
        foreach ($catalogue as $base) {
            $product = $submitted[$base->id] ?? null;
            if (!$product instanceof ConditionProduct) {
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
