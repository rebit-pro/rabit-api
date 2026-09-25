<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Catalog\ValueObject;

use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Catalog\Exception\InvalidProductException;

final readonly class ProductDetails
{
    /** Предел цены товара в копейках (1 000 000 ₽, решение E6-DEC-02): цена продажи с учётом расходов остаётся в INT. */
    public const int MAX_PRICE = 100_000_000;

    public function __construct(
        public string $name,
        public string $description,
        public ProductKind $kind,
        public int $price,
        public int $printCount,
        public string $format,
        public string $unit,
        public bool $staffDiscount,
        public bool $active,
    ) {
        self::validateText($name, 255, 'name');
        self::validateText($description, 4000, 'description');
        self::validateText($format, 100, 'format');
        self::validateText($unit, 100, 'unit');
        if ('' === trim($name)) {
            throw new InvalidProductException('Product name must not be blank.');
        }
        if (0 > $price || self::MAX_PRICE < $price) {
            throw new InvalidProductException('Price must be between 0 and 100000000 minor units.');
        }
        if (0 > $printCount || 2147483647 < $printCount) {
            throw new InvalidProductException('printCount must fit a nonnegative signed 32-bit integer.');
        }
    }

    private static function validateText(string $value, int $limit, string $field): void
    {
        if (!mb_check_encoding($value, 'UTF-8') || $limit < mb_strlen($value, 'UTF-8') || str_contains($value, "\0")) {
            throw new InvalidProductException('Invalid UTF-8 text or exceeded length for ' . $field . '.');
        }
    }
}
