<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Institution\ValueObject;

use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;

/** Неизменяемый состав выпуска учреждения и изменяемое состояние его файла, аренды и поколения доступа. */
final readonly class InstitutionArchive
{
    /** @param list<InstitutionArchiveGroup> $groups */
    public function __construct(
        public string $publicId,
        public int $institutionId,
        public string $institutionPublicId,
        public int $number,
        public string $kind,
        public string $fingerprint,
        public array $groups,
        public int $lines,
        public int $files,
        public int $prints,
        public VersionStatusEnum $status,
        public ?string $error,
        public int $attempts,
        public int $generation,
        public ?string $archivePath,
        public ?string $workPath,
        public ?string $obsoletePath,
        public ?int $bytes,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $readyAt,
        public ?\DateTimeImmutable $firstReadyAt,
        public ?\DateTimeImmutable $expiresAt,
    ) {}
}
