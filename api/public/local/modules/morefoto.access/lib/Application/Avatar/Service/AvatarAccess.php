<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Avatar\Service;

use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Domain\Staff\Enum\PermissionEnum;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Решает, кто работает с аватарами: свой меняет любой сотрудник с включённым доступом, чужой — только с правом
 * управления сотрудниками; смотреть аватары коллег может любой сотрудник с доступом.
 */
final readonly class AvatarAccess
{
    public function __construct(
        private StaffAuthorization $authorization,
    ) {}

    /**
     * @throws HttpException
     */
    public function assertCanChange(int $actorUserId, int $userId): void
    {
        if ($actorUserId === $userId) {
            $this->authorization->context($actorUserId);

            return;
        }
        $this->authorization->assertCan($actorUserId, PermissionEnum::STAFF_MANAGE);
    }

    /**
     * @throws HttpException
     */
    public function assertCanView(int $actorUserId): void
    {
        $this->authorization->context($actorUserId);
    }
}
