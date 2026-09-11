<?php

declare(strict_types=1);

namespace Rebit\Auth\Domain\User\Entity;

use Bitrix\Main\Type\DateTime;

final readonly class UserToken
{
    public function __construct(
        public int $userId,
        public ?DateTime $expiresAt,
    ) {}
}
