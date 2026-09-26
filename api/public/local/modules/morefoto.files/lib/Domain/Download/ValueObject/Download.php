<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Download\ValueObject;

use Morefoto\Files\Domain\Download\Enum\DownloadKindEnum;
use Morefoto\Files\Domain\Download\Enum\DownloadStatusEnum;

/** Запрос скачивания: одиночный оригинал или архив с зафиксированным составом кадров. */
final readonly class Download
{
    /**
     * @param list<string> $photoIds    состав в каноническом порядке
     * @param null|string  $archivePath путь ZIP закрепляется при создании, чтобы purge убрал архив после любого сбоя сборки
     */
    public function __construct(
        public int $id,
        public string $publicId,
        public int $orderId,
        public DownloadKindEnum $kind,
        public DownloadStatusEnum $status,
        public array $photoIds,
        public string $compositionHash,
        public string $filename,
        public ?string $archivePath,
        public ?int $bytes,
        public ?string $errorCode,
        public int $attempts,
        public ?\DateTimeImmutable $nextAttemptAt,
        public ?\DateTimeImmutable $expiresAt,
    ) {}
}
