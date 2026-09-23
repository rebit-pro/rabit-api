<?php

declare(strict_types=1);

namespace Rebit\Auth\Domain\Access\Entity;

use Rebit\Auth\Domain\Access\Enum\AccessLinkPurposeEnum;

/** One-time personal link: only the SHA-256 of its token is stored, a newer link replaces the older one. */
final readonly class AccessLink
{
    public function __construct(
        public int $id,
        public int $userId,
        public AccessLinkPurposeEnum $purpose,
        public string $tokenHash,
        public int $issuedAt,
        public int $expiresAt,
        public int $resendAvailableAt,
        public ?int $usedAt,
        public ?int $issuedBy,
    ) {}

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isUsed(): bool
    {
        return null !== $this->usedAt;
    }

    public function isExpired(int $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function isResendBlocked(int $now): bool
    {
        return !$this->isUsed() && $this->resendAvailableAt > $now;
    }
}
