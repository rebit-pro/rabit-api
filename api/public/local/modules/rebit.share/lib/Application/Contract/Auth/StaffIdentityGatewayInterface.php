<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Auth;

use Rebit\Share\Application\Contract\Auth\Dto\StaffIdentityOutputDto;

interface StaffIdentityGatewayInterface
{
    public function findByEmailForUpdate(string $email): ?StaffIdentityOutputDto;

    public function lockById(int $userId): ?StaffIdentityOutputDto;

    public function createPending(string $email, string $name): StaffIdentityOutputDto;

    public function updateContact(int $userId, string $email, string $name): StaffIdentityOutputDto;

    public function revokeSessions(int $userId): void;
}
