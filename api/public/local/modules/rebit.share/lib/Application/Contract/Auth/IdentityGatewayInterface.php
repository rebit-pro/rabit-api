<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Auth;

use Rebit\Share\Application\Contract\Auth\Dto\IdentityOutputDto;

interface IdentityGatewayInterface
{
    public function findActive(int $userId): ?IdentityOutputDto;

    /** Caller owns the local transaction; lock the identity before privileged changes. */
    public function lockActive(int $userId): ?IdentityOutputDto;

    /** Caller owns the local transaction and identity lock. No implicit commit. */
    public function revokeSessions(int $userId): void;
}
