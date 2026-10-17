<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Receipt\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class ReceiptResultDto implements ResultDtoInterface
{
    /** @param list<ReceiptItemResultDto> $items */
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
