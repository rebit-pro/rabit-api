<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\Dto;

final readonly class ReceiptOutputDto
{
    /**
     * @param string                     $kind      payment — чек оплаты, closing — закрывающий чек печати
     * @param string                     $status    pending | sent | failed
     * @param list<ReceiptItemOutputDto> $items
     * @param string                     $createdAt бизнес-время Europe/Moscow ATOM
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $status,
        public int $amount,
        public array $items,
        public string $createdAt,
        public ?string $registeredAt,
        public ?string $fiscalDocumentNumber,
        public ?string $fiscalStorageNumber,
        public ?string $fiscalAttribute,
        public ?string $handedAt,
    ) {}
}
