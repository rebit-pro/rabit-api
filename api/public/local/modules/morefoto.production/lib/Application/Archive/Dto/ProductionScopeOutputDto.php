<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Dto;

final readonly class ProductionScopeOutputDto
{
    /**
     * @param null|list<int> $institutionIds учреждения куратора; null — организатор видит все
     * @param bool           $canManage      собирать версии и перевыпускать ссылку может только организатор (L1-DEC-07)
     */
    public function __construct(
        public string $role,
        public ?array $institutionIds,
        public bool $canManage,
    ) {}
}
