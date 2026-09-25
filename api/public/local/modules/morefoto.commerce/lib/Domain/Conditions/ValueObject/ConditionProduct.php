<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Conditions\ValueObject;

use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductDetails;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductId;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;

/** Проверенная строка условий продажи товара: базовая цена, доступность и льгота сотрудникам. */
final readonly class ConditionProduct
{
    public ProductId $id;

    public function __construct(
        string $id,
        public int $price,
        public bool $active,
        public bool $staffDiscount,
    ) {
        try {
            $this->id = new ProductId($id);
        } catch (\Throwable $exception) {
            throw new InvalidConditionsException('Invalid product ID.', 0, $exception);
        }
        if (0 > $price || ProductDetails::MAX_PRICE < $price) {
            throw new InvalidConditionsException('Product price is outside the supported range.');
        }
    }
}
