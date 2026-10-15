<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Archive\ValueObject;

/** Оплаченная печатная строка заказа в составе версии и её файл внутри архива. */
final readonly class ArchiveLine
{
    /**
     * @param string $paidAt подтверждённая оплата, UTC `Y-m-d H:i:s`
     * @param int    $prints quantity × printCount
     * @param string $entry  путь файла внутри архива без расширения: `<продукция>/<код кадра>_x<отпечатков>`
     */
    public function __construct(
        public string $orderLineId,
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
        public int $prints,
        public string $entry,
    ) {}
}
