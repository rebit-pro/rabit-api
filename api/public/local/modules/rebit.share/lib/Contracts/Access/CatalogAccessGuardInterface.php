<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Access;

interface CatalogAccessGuardInterface
{
    /**
     * Participant in the caller's local transaction; never starts or commits one.
     * Lock order: Access state, staff profile, Auth identity. Revalidate token after locks.
     * Locks remain held until the caller commits, including an idempotency replay.
     *
     * @throws CatalogAccessException
     */
    public function lockOrganizer(int $actorId, string $token): void;
}
