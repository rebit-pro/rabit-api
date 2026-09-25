<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\Dto;

use Morefoto\Access\Domain\Staff\Enum\AccountStatusEnum;
use Morefoto\Access\Domain\Staff\Enum\RoleEnum;

final readonly class ListStaffInputDto
{
    public function __construct(
        public string $query,
        public ?RoleEnum $role,
        public ?bool $active,
        public ?AccountStatusEnum $accountStatus,
        public int $page,
        public int $pageSize,
    ) {
        if (100 < mb_strlen($query) || 1 > $page || 1000000 < $page || 1 > $pageSize || 100 < $pageSize) {
            throw new \InvalidArgumentException('Invalid staff list filters.');
        }
    }
}
