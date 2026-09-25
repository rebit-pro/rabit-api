<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

final readonly class PaymentStaffScopeOutputDto
{
    /** @param null|list<int> $institutionIds null — весь проект */
    public function __construct(
        public string $role,
        public int $accessRevision,
        public ?array $institutionIds,
    ) {}
}
