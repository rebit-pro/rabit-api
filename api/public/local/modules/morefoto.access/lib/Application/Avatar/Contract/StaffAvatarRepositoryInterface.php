<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Avatar\Contract;

use Morefoto\Access\Domain\Avatar\Entity\StaffAvatar;
use Rebit\Share\Shared\Exception\HttpException;

interface StaffAvatarRepositoryInterface
{
    public function find(int $userId): ?StaffAvatar;

    /**
     * Runs the operation in one transaction that holds the staff profile row of the user: avatar changes of one
     * employee are serialized without the global access lock.
     *
     * @template T
     *
     * @param callable(?StaffAvatar): T $operation receives the current avatar read under the lock
     *
     * @return T
     *
     * @throws HttpException STAFF_NOT_FOUND when the user has no staff profile
     */
    public function locked(int $userId, callable $operation): mixed;

    public function save(StaffAvatar $avatar, int $updatedBy): void;

    public function delete(int $userId): void;
}
