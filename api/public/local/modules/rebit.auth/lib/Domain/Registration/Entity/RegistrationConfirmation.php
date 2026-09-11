<?php

declare(strict_types=1);

namespace Rebit\Auth\Domain\Registration\Entity;

use Bitrix\Main\Type\DateTime;

final readonly class RegistrationConfirmation
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $email,
        public string $codeHash,
        public DateTime $codeExpiresAt,
        public DateTime $resendAvailableAt,
        public int $attempts,
        public ?DateTime $confirmedAt,
        public DateTime $createdAt,
        public DateTime $updatedAt,
    ) {}
}
