<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\Contract;

use Rebit\Auth\Application\Access\Dto\AccessAccountDto;

/** Staff account operations needed by access links; `lock*` reads hold the row until the caller commits. */
interface AccessAccountInterface
{
    public function findById(int $userId): ?AccessAccountDto;

    public function lockById(int $userId): ?AccessAccountDto;

    public function lockByEmail(string $email): ?AccessAccountDto;

    public function changePassword(int $userId, string $password): void;

    public function activate(int $userId): void;
}
