<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Payment\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class PaymentListResultDto implements ResultDtoInterface
{
    /** @param list<PaymentItemResultDto> $items */
    public function __construct(public array $items) {}
}
