<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Archive\ValueObject;

use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;

/** Снимок новой версии до записи: строки вставляются с ней в одной транзакции. */
final readonly class NewVersion
{
    /** @param list<ArchiveLine> $lines */
    public function __construct(
        public string $publicId,
        public int $groupId,
        public string $groupPublicId,
        public int $institutionId,
        public \DateTimeImmutable $deadlineAt,
        public int $number,
        public VersionStatusEnum $status,
        public int $filesCount,
        public int $printsCount,
        public int $waitingOrders,
        public string $archivePath,
        public array $lines,
        public \DateTimeImmutable $createdAt,
        public ?int $createdBy,
    ) {}
}
