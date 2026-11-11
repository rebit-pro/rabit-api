<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Institution\Service;

use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Institution\Dto\InstitutionVersionOutputDto;
use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchive;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchiveGroup;
use Rebit\Share\Application\Contract\Clock\ClockInterface;

/** Показывает сохранённый выпуск с учётом истечения срока и фактического наличия ZIP, исключая ложную готовность. */
final readonly class InstitutionArchiveView
{
    public function __construct(private ArchiveStorageInterface $storage, private ClockInterface $clock) {}

    public function one(InstitutionArchive $archive): InstitutionVersionOutputDto
    {
        $state = $archive->status;
        $error = $archive->error;
        if (VersionStatusEnum::READY === $state) {
            if (null === $archive->expiresAt || $this->clock->now() >= $archive->expiresAt) {
                $state = VersionStatusEnum::EXPIRED;
            } elseif (null === $archive->archivePath || !$this->storage->exists($archive->archivePath)) {
                $state = VersionStatusEnum::FAILED;
                $error = 'ARCHIVE_MISSING';
            }
        }

        return new InstitutionVersionOutputDto(
            id: $archive->publicId,
            number: $archive->number,
            kind: $archive->kind,
            status: $state->value,
            lines: $archive->lines,
            files: $archive->files,
            prints: $archive->prints,
            generation: $archive->generation,
            groups: array_map(static fn(InstitutionArchiveGroup $group): string => $group->name, $archive->groups),
            error: $error,
            bytes: $archive->bytes,
            createdAt: $archive->createdAt,
            readyAt: $archive->readyAt,
            expiresAt: $archive->expiresAt,
        );
    }
}
