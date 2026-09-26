<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Download\Service;

use Morefoto\Files\Domain\Download\Enum\FilesStateEnum;
use Rebit\Share\Shared\Exception\HttpException;

/** Предметные правила выдачи купленных файлов: кто и до какого момента получает оригиналы и какие архивы допустимы.
 * Решения J1-DEC-04: архив до 500 файлов и 2 ГиБ, готовая загрузка живёт сутки, ссылка — 10 минут, всё в пределах срока D10.
 */
final readonly class FileAccessPolicy
{
    public const int MAX_ARCHIVE_FILES = 500;
    public const int MAX_ARCHIVE_BYTES = 2 * 1024 * 1024 * 1024;
    public const int MAX_ATTEMPTS = 3;
    private const string DOWNLOAD_LIFETIME = '+24 hours';
    private const string LINK_LIFETIME = '+10 minutes';
    private const string BUILD_LEASE = '+15 minutes';
    private const string RETRY_DELAY = '+1 minute';

    /** Поздняя оплата без решения исполнения не открывает файлы (G1-D12-SCOPE); ровно на границе срока доступ закрыт (D10). */
    public function state(string $paymentStatus, bool $latePayment, ?\DateTimeImmutable $availableUntil, \DateTimeImmutable $now, bool $hasFiles): FilesStateEnum
    {
        return match (true) {
            'paid' !== $paymentStatus || null === $availableUntil => FilesStateEnum::UNPAID,
            $latePayment => FilesStateEnum::REVIEW,
            $now >= $availableUntil => FilesStateEnum::EXPIRED,
            !$hasFiles => FilesStateEnum::EMPTY,
            default => FilesStateEnum::AVAILABLE,
        };
    }

    public function assertArchiveFits(int $files, int $bytes): void
    {
        if (self::MAX_ARCHIVE_FILES < $files || self::MAX_ARCHIVE_BYTES < $bytes) {
            throw new HttpException('ARCHIVE_TOO_LARGE', 413, null, ['maxFiles' => self::MAX_ARCHIVE_FILES, 'maxBytes' => self::MAX_ARCHIVE_BYTES]);
        }
    }

    public function downloadExpiresAt(\DateTimeImmutable $now, \DateTimeImmutable $availableUntil): \DateTimeImmutable
    {
        return min($now->modify(self::DOWNLOAD_LIFETIME), $availableUntil);
    }

    public function failedRetainedUntil(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->modify(self::DOWNLOAD_LIFETIME);
    }

    public function linkExpiresAt(\DateTimeImmutable $now, \DateTimeImmutable $downloadExpiresAt): \DateTimeImmutable
    {
        return min($now->modify(self::LINK_LIFETIME), $downloadExpiresAt);
    }

    public function leaseUntil(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->modify(self::BUILD_LEASE);
    }

    public function retryAt(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->modify(self::RETRY_DELAY);
    }
}
