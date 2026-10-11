<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Storefront\Service;

use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Правило двух сроков продаж E7: печать (`physical`) продаётся до закрытия приёма печати, электронные фото
 * (`digital`, комплект `bundle` и подарок-комплект) — до окончания продажи электронных. Классифицирует фактический
 * состав корзины и заказа, а не название товара, и выбирает срок, после которого заказ уже нельзя оплатить. Пока
 * исправляется разметка переданной группы (D4), продажа на паузе.
 */
final readonly class SalesWindowPolicy
{
    /** Состояние группы допускает хотя бы одну покупку: open — всё, digital — только электронные. */
    public function assertSalesOpen(string $state): void
    {
        if ('open' !== $state && 'digital' !== $state) {
            throw new HttpException('GALLERY_CLOSED', 409);
        }
    }

    /** D4: пока исправляется разметка, покупатель не рассчитывает корзину и не оформляет заказ по промежуточным кадрам. */
    public function assertNotPaused(?string $markupCorrectionAt): void
    {
        if (null !== $markupCorrectionAt) {
            throw new HttpException('GALLERY_UPDATING', 409);
        }
    }

    public function assertSellable(string $state, ProductKind $kind): void
    {
        $this->assertSalesOpen($state);
        if ('digital' === $state && ProductKind::PHYSICAL === $kind) {
            throw new HttpException('PRINT_SALES_CLOSED', 409);
        }
    }

    /** Заказ с печатью оплачивается до закрытия печати, остальной — до окончания продажи электронных; null — срок не назначен. */
    public function salesUntil(bool $containsPrint, ?string $closesAt, ?string $digitalSalesUntil): ?string
    {
        return $containsPrint ? $closesAt : $digitalSalesUntil;
    }
}
