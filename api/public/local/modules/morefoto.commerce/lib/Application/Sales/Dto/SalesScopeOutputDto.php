<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Dto;

final readonly class SalesScopeOutputDto
{
    /**
     * @param null|list<int> $institutionIds null — без ограничения по учреждениям
     * @param null|list<int> $groupIds       null — без ограничения по группам
     */
    public function __construct(public string $role, public ?array $institutionIds, public ?array $groupIds) {}
}
