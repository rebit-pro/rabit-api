<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Avatar\Entity;

/** Current avatar of an employee: the version grows only with new content, the fingerprint is sha256 of the upload. */
final readonly class StaffAvatar
{
    public function __construct(
        public int $userId,
        public int $version,
        public string $fingerprint,
        public string $mimeType,
        public int $bytes,
        public int $width,
        public int $height,
    ) {}

    public function hasContent(string $fingerprint): bool
    {
        return hash_equals($this->fingerprint, $fingerprint);
    }
}
