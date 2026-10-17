<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Receipt\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class PrintHandoverOrderResultDto implements ResultDtoInterface
{
    /** @param list<string> $childCodes */
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
