<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce\Dto;

final readonly class PaidPrintLineOutputDto
{
    /**
     * Печатная строка оплаченного заказа на момент покупки.
     *
     * @param string $lineId     публичный ID строки заказа
     * @param string $paidAt     подтверждённая оплата, UTC `Y-m-d H:i:s`
     * @param string $audience   regular | staff — тип группы, в которой оформлен заказ
     * @param string $photoCode  код кадра ребёнка (код ребёнка + номер кадра)
     * @param int    $printCount отпечатков в одной единице продукции
     * @param int    $quantity   количество единиц продукции
     */
    public function __construct(
        public string $lineId,
        public string $orderId,
        public string $orderNumber,
        public string $paidAt,
        public string $audience,
        public string $childCode,
        public string $photoId,
        public string $photoCode,
        public string $productId,
        public string $productName,
        public string $productFormat,
        public int $printCount,
        public int $quantity,
    ) {}
}
