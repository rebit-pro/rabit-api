<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Access;

interface AccessGuardInterface
{
    /**
     * userId comes from validated Auth, resource ancestry from the owning module's DB.
     * UI permissions and client-supplied institution/group IDs are not proof of authority.
     * Resource existence and permitted list filtering remain the caller's responsibility.
     * Throws on unknown actions, missing/blocked staff or out-of-scope resources.
     */
    public function assertCan(int $userId, string $action, ?int $institutionId = null, ?int $groupId = null): void;
}
