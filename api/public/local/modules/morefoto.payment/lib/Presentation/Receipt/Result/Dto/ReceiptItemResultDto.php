<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Receipt\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class ReceiptItemResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $description,
        public int $quantity,
        public int $amount,
        public string $paymentMode,
    ) {}
}
