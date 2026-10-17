<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Receipt\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class OrderReceiptsResultDto implements ResultDtoInterface
{
    /** @param list<ReceiptResultDto> $receipts */
    public function __construct(
        public bool $enabled,
        public ?string $channel,
        public array $receipts,
    ) {}
}
