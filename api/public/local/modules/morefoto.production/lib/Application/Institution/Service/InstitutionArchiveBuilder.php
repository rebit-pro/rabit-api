<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Institution\Service;

use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Archive\Contract\ArchiveWriterInterface;
use Morefoto\Production\Application\Archive\Contract\ManifestWriterInterface;
use Morefoto\Production\Application\Archive\Contract\ProductionIdGeneratorInterface;
use Morefoto\Production\Application\Archive\Dto\ArchiveFileInputDto;
use Morefoto\Production\Domain\Archive\Exception\ProductionStorageException;
use Morefoto\Production\Domain\Archive\Service\ArchivePolicy;
use Morefoto\Production\Domain\Institution\Repository\InstitutionArchiveRepositoryInterface;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchive;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Собирает один ZIP учреждения по сохранённым заданиям, повторяя серверную проверку календаря до публикации.
 * Каждая аренда пишет отдельный файл; только действующий владелец публикует его, пропавший оригинал даёт ошибку.
 * Перед пересборкой удаляет прежний ZIP по сохранённому пути, который остаётся доступен для повтора при сбое очистки.
 */
final readonly class InstitutionArchiveBuilder
{
    public function __construct(
        private InstitutionArchiveRepositoryInterface $archives,
        private InstitutionSnapshot $snapshot,
        private InstitutionGroups $groups,
        private OriginalFilesInterface $originals,
        private ArchiveWriterInterface $writer,
        private ManifestWriterInterface $manifest,
        private ArchiveStorageInterface $storage,
        private ProductionIdGeneratorInterface $ids,
        private ArchivePolicy $policy,
        private ClockInterface $clock,
    ) {}

    public function build(InstitutionArchive $due): bool
    {
        $owner = $this->ids->uuid();
        $path = $this->storage->archivePath($due->institutionPublicId, $owner);
        $now = $this->clock->now();
        if (!$this->archives->claim($due->publicId, $owner, $path, $now, $this->policy->leaseUntil($now))) {
            return false;
        }
        $archive = $this->archives->find($due->publicId) ?? throw new ProductionStorageException('Claimed institution archive is missing.');
        try {
            if (null !== $due->workPath && $due->workPath !== $path) {
                $this->storage->delete($due->workPath);
            }
            if (null !== $archive->obsoletePath) {
                $this->storage->delete($archive->obsoletePath);
            }
            if (ArchivePolicy::MAX_ATTEMPTS < $archive->attempts) {
                throw new HttpException('ARCHIVE_FAILED', 409);
            }
            $this->groups->guard($archive, $now);
            $lines = $this->snapshot->lines($archive);
            $photoIds = [];
            foreach ($lines as $line) {
                $photoIds[$line->photoId] = $line->photoId;
            }
            $originals = $this->originals->originals(array_values($photoIds));
            $files = [];
            $entryPhotos = [];
            foreach ($lines as $line) {
                $original = $originals[$line->photoId] ?? throw new HttpException('ORIGINAL_MISSING', 409);
                if (isset($entryPhotos[$line->entry]) && $entryPhotos[$line->entry] !== $line->photoId) {
                    throw new ProductionStorageException('Conflicting institution entries.');
                }
                $entryPhotos[$line->entry] = $line->photoId;
                $files[$line->entry] = new ArchiveFileInputDto($original->absolutePath, $line->entry);
            }
            if (count($files) !== $archive->files) {
                throw new ProductionStorageException('Institution file count changed.');
            }
            $bytes = $this->writer->write(array_values($files), $this->manifest->csv($lines, [], 2), $this->storage->absolutePath($path));
            $readyAt = $this->clock->now();
            $this->groups->guard($archive, $readyAt);
            if ($this->archives->ready($archive->publicId, $owner, $bytes, $readyAt, $this->policy->expiresAt($readyAt))) {
                return true;
            }
            $this->storage->delete($path);

            return false;
        } catch (\Throwable $error) {
            $this->storage->delete($path);
            $code = $error instanceof HttpException && in_array($error->getMessage(), ['ORIGINAL_MISSING', 'COMPOSITION_CHANGED', 'PRINT_SALES_OPEN', 'BUILD_DELAY'], true)
                ? $error->getMessage() : 'ARCHIVE_FAILED';
            $retry = $error instanceof HttpException || ArchivePolicy::MAX_ATTEMPTS <= $archive->attempts ? null : $this->policy->retryAt($now);
            $this->archives->failed($archive->publicId, $owner, $code, $retry);

            return false;
        }
    }
}
