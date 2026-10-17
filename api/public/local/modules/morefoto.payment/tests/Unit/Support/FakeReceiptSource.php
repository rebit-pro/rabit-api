<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit\Support;

use Rebit\Share\Contracts\Commerce\Dto\OrderReceiptLineOutputDto;
use Rebit\Share\Contracts\Commerce\Dto\OrderReceiptSourceOutputDto;
use Rebit\Share\Contracts\Commerce\OrderReceiptSourceInterface;

/** Снимок заказа для чека: по умолчанию две единицы печати и электронный кадр на 105 000 копеек. */
final class FakeReceiptSource implements OrderReceiptSourceInterface
{
    /** @param list<OrderReceiptLineOutputDto> $lines */
    public function __construct(public array $lines = []) {}

    public function find(int $orderId): OrderReceiptSourceOutputDto
    {
        return new OrderReceiptSourceOutputDto('parent@example.test', [] !== $this->lines ? $this->lines : [
            new OrderReceiptLineOutputDto('physical', 'Печать 10×15', 2, 30000, 60000),
            new OrderReceiptLineOutputDto('digital', 'Электронная фотография', 1, 45000, 45000),
        ]);
    }
}
