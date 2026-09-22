<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

final readonly class OrderStaffScopeOutputDto
{
    /** @param null|list<int> $institutionIds null — весь проект */
    public function __construct(public string $role, public int $accessRevision, public ?array $institutionIds) {}
}
