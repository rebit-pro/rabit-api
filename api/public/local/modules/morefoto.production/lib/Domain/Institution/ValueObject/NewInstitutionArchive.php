<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Institution\ValueObject;

/** Новая версия доставки: источники L1 и назначенные пути, без второго учёта печатных строк. */
final readonly class NewInstitutionArchive
{
    /** @param list<InstitutionArchiveGroup> $groups */
    public function __construct(
        public string $publicId,
        public int $institutionId,
        public string $institutionPublicId,
        public string $kind,
        public string $fingerprint,
        public array $groups,
        public int $lines,
        public int $files,
        public int $prints,
        public \DateTimeImmutable $createdAt,
    ) {}
}
