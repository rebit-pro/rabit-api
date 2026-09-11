<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Auth;

/** Revokes only the supplied current session; a newer login must survive. */
interface TokenRevokerInterface
{
    public function revokeToken(int $userId, string $token): void;
}
