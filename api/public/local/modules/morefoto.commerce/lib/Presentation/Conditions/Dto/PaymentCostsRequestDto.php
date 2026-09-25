<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Conditions\Dto;

final readonly class PaymentCostsRequestDto
{
    public function __construct(
        public bool $enabled,
        public int $rateBps,
    ) {}
}
