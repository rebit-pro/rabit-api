<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\Dto;

final readonly class PrintHandoverOrderOutputDto
{
    /**
     * @param list<string> $childCodes    коды детей в печати заказа
     * @param int          $prints        отпечатков: сумма quantity × printCount
     * @param string       $paidAt        бизнес-время Europe/Moscow ATOM
     * @param string       $state         waiting | handed | blocked
     * @param null|string  $receiptStatus закрывающий чек: pending | sent | failed
     * @param null|string  $reason        blocked: no_payment_receipt | payment_receipt_failed
     */
    public function __construct(
        public string $orderId,
        public string $orderNumber,
        public array $childCodes,
        public int $prints,
        public string $paidAt,
        public string $state,
        public ?string $handedAt,
        public ?string $receiptStatus,
        public ?string $reason,
    ) {}
}
