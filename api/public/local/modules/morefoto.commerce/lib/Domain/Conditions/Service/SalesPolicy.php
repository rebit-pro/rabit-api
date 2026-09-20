<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Conditions\Service;

use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;
use Morefoto\Commerce\Domain\Conditions\ValueObject\PricedSaleItem;
use Morefoto\Commerce\Domain\Conditions\ValueObject\SaleItem;
use Morefoto\Commerce\Domain\Conditions\ValueObject\SalesProduct;
use Morefoto\Commerce\Domain\Conditions\ValueObject\SalesQuote;

final readonly class SalesPolicy
{
    /** @var array<string, SalesProduct> */
    private array $products;
    private ?SalesProduct $gift;

    /** @param list<SalesProduct> $products */
    public function __construct(
        public int $catalogRevision,
        public int $conditionsRevision,
        array $products,
        public int $giftThreshold,
        public bool $giftForStaff,
    ) {
        if (1 > $catalogRevision || 1 > $conditionsRevision || 0 > $giftThreshold) {
            throw new InvalidConditionsException('Positive policy revisions and a non-negative threshold are required.');
        }
        $map = [];
        $gifts = [];
        foreach ($products as $product) {
            if (isset($map[$product->id->value])) {
                throw new InvalidConditionsException('Duplicate policy product.');
            }
            $map[$product->id->value] = $product;
            if ($product->active && ProductKind::BUNDLE === $product->kind) {
                $gifts[] = $product;
            }
        }
        if (0 < $giftThreshold && 1 !== count($gifts)) {
            throw new InvalidConditionsException('A gift policy requires exactly one active bundle product.');
        }
        $this->products = $map;
        $this->gift = $gifts[0] ?? null;
    }

    /** @param list<SaleItem> $source */
    public function quote(array $source, bool $confirmedStaff): SalesQuote
    {
        $items = [];
        $invalid = [];
        $printed = [];
        $bundleChildren = [];
        foreach ($source as $item) {
            $product = $this->products[$item->productId->value] ?? null;
            if (!$product instanceof SalesProduct || !$product->active || !$this->hasValidPhoto($item, $product)
                || (ProductKind::BUNDLE === $product->kind && isset($bundleChildren[$item->childId]))) {
                $invalid[] = $item->id;
                continue;
            }
            if (ProductKind::BUNDLE === $product->kind) {
                $bundleChildren[$item->childId] = true;
            }
            $quantity = ProductKind::PHYSICAL === $product->kind ? $item->quantity : 1;
            $subtotal = $this->multiply($product->price, $quantity);
            $unitPrice = $confirmedStaff && $product->staffDiscount ? intdiv($product->price + 1, 2) : $product->price;
            $total = $this->multiply($unitPrice, $quantity);
            $discount = $subtotal - $total;
            $items[] = new PricedSaleItem($item, $product, $quantity, $unitPrice, $subtotal, $discount, 0, $total, false);
            if (ProductKind::PHYSICAL === $product->kind) {
                $printed[$item->childId] = $this->add($printed[$item->childId] ?? 0, $total);
            }
        }
        $gifts = [];
        if (0 < $this->giftThreshold && (!$confirmedStaff || $this->giftForStaff) && $this->gift instanceof SalesProduct) {
            foreach ($printed as $childId => $amount) {
                if ($amount >= $this->giftThreshold) {
                    $gifts[$childId] = $this->gift->id->value;
                }
            }
        }
        $priced = [];
        $subtotal = 0;
        $staffDiscount = 0;
        $giftSaving = 0;
        $total = 0;
        foreach ($items as $item) {
            if (ProductKind::BUNDLE === $item->product->kind && isset($gifts[$item->item->childId])) {
                // Gift wins over the staff discount; the same line never stacks both reductions.
                $item = new PricedSaleItem($item->item, $item->product, $item->quantity, $item->product->price, $item->subtotal, 0, $item->subtotal, 0, true);
            }
            $priced[] = $item;
            $subtotal = $this->add($subtotal, $item->subtotal);
            $staffDiscount = $this->add($staffDiscount, $item->staffDiscount);
            $giftSaving = $this->add($giftSaving, $item->giftSaving);
            $total = $this->add($total, $item->total);
        }

        return new SalesQuote($this->catalogRevision, $this->conditionsRevision, $priced, $gifts, $invalid, $subtotal, $staffDiscount, $giftSaving, $total);
    }

    private function hasValidPhoto(SaleItem $item, SalesProduct $product): bool
    {
        return ProductKind::BUNDLE === $product->kind ? null === $item->photoId : null !== $item->photoId && '' !== trim($item->photoId);
    }

    private function multiply(int $left, int $right): int
    {
        if (0 !== $left && intdiv(PHP_INT_MAX, $left) < $right) {
            throw new InvalidConditionsException('Calculated amount exceeds the supported range.');
        }

        return $left * $right;
    }

    private function add(int $left, int $right): int
    {
        if (PHP_INT_MAX - $left < $right) {
            throw new InvalidConditionsException('Calculated amount exceeds the supported range.');
        }

        return $left + $right;
    }
}
