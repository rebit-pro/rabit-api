<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Payment\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class PaymentAcknowledgedResultDto implements ResultDtoInterface
{
    public function __construct(public bool $acknowledged) {}
}
