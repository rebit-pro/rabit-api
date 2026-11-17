<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Institution\Service;

use Morefoto\Files\Domain\Institution\Exception\DigitalArchiveException;

/** Ограничивает один служебный ZIP без усечения состава и хранит готовый файл семь суток по решению владельца. */
final readonly class DigitalArchivePolicy
{
    public const int MAX_FILES = 10_000;
    public const int MAX_BYTES = 20 * 1024 * 1024 * 1024;
    public const int MAX_SOURCES = 50_000;
    public const int MAX_ATTEMPTS = 3;

    public function assertFits(int $files, int $bytes, int $sources): void
    {
        if (self::MAX_FILES < $files || self::MAX_BYTES < $bytes || self::MAX_SOURCES < $sources) {
            throw new DigitalArchiveException('DIGITAL_ARCHIVE_TOO_LARGE');
        }
    }

    public function expiresAt(\DateTimeImmutable $readyAt): \DateTimeImmutable
    {
        return $readyAt->add(new \DateInterval('P7D'));
    }

    public function leaseUntil(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->add(new \DateInterval('PT30M'));
    }

    public function retryAt(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->add(new \DateInterval('PT1M'));
    }
}
