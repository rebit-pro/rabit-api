<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Staff\Entity;

use Morefoto\Access\Domain\Staff\Enum\RoleEnum;

final readonly class StaffProfile
{
    public function __construct(
        public int $userId,
        public ?RoleEnum $role,
        public bool $active,
        public int $revision,
        public int $accessRevision,
    ) {}

    /**
     * @param array{
     *     UF_USER_ID: int|string,
     *     UF_ROLE: string,
     *     UF_ACTIVE: int|string,
     *     UF_REVISION: int|string,
     *     UF_ACCESS_REVISION: int|string,
     * }|false $row
     */
    public static function fromRow(array|false $row): ?self
    {
        return false === $row ? null : new self(
            userId: (int)$row['UF_USER_ID'],
            role: RoleEnum::tryFrom($row['UF_ROLE']),
            active: 1 === (int)$row['UF_ACTIVE'],
            revision: (int)$row['UF_REVISION'],
            accessRevision: (int)$row['UF_ACCESS_REVISION'],
        );
    }

    public function isEnabled(): bool
    {
        return 0 < $this->userId && $this->active && null !== $this->role
            && 0 < $this->revision && 0 < $this->accessRevision;
    }
}
