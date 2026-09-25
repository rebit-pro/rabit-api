<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\Dto;

final readonly class PaymentCostsOutputDto
{
    public function __construct(
        public bool $enabled,
        public int $rateBps,
        public int $roundingStep,
        public int $maxRateBps,
    ) {}
}
