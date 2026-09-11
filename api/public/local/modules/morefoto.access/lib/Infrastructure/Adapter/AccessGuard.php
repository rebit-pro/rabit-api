<?php

declare(strict_types=1);

namespace Morefoto\Access\Infrastructure\Adapter;

use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Domain\Staff\Enum\PermissionEnum;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class AccessGuard implements AccessGuardInterface
{
    public function __construct(private StaffAuthorization $authorization) {}

    public function assertCan(int $userId, string $action, ?int $institutionId = null, ?int $groupId = null): void
    {
        $permission = PermissionEnum::tryFrom($action);
        if (null === $permission) {
            throw new HttpException('Action is forbidden.', 403);
        }
        $this->authorization->assertCan($userId, $permission, $institutionId, $groupId);
    }
}
