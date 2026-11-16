<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class WorksheetOrderResultDto implements ResultDtoInterface
{
    /**
     * @param list<string>                 $childCodes
     * @param list<string>                 $giftCodes  подарочные комплекты из снимка, не покупки
     * @param list<WorksheetLineResultDto> $lines
     */
    public function __construct(
        public string $id,
        public string $number,
        public ?string $firstName,
        public ?string $lastName,
        public array $childCodes,
        public bool $ambiguous,
        public string $paymentStatus,
        public bool $latePayment,
        public int $total,
        public int $includedTotal,
        public int $discount,
        public int $electronicTotal,
        public array $giftCodes,
        public array $lines,
    ) {}
}
