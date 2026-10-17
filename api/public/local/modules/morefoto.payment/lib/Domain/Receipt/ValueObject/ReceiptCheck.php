<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Receipt\ValueObject;

use Morefoto\Payment\Domain\Receipt\Enum\ReceiptStatusEnum;

/** Итог одной сверки чека; моменты — UTC `Y-m-d H:i:s`, nextCheckAt null — сверка завершена. */
final readonly class ReceiptCheck
{
    public function __construct(
        public ReceiptStatusEnum $status,
        public string $checkedAt,
        public ?string $nextCheckAt,
        public ?string $providerReceiptId = null,
        public ?string $error = null,
        public ?string $fiscalDocumentNumber = null,
        public ?string $fiscalStorageNumber = null,
        public ?string $fiscalAttribute = null,
        public ?string $registeredAt = null,
    ) {}
}
