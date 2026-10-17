<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

final readonly class ProviderReceiptOutputDto
{
    /**
     * @param string      $type         payment — приход, refund — возврат прихода
     * @param string      $status       pending, succeeded, canceled
     * @param null|string $registeredAt момент регистрации в фискальном накопителе, UTC `Y-m-d H:i:s`
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $status,
        public ?string $fiscalDocumentNumber,
        public ?string $fiscalStorageNumber,
        public ?string $fiscalAttribute,
        public ?string $registeredAt,
    ) {}
}
