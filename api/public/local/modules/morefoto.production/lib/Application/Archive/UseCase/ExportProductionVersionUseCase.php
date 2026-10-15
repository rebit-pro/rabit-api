<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\UseCase;

use Morefoto\Production\Application\Archive\Contract\ManifestWriterInterface;
use Morefoto\Production\Application\Archive\Contract\ProductionAccessInterface;
use Morefoto\Production\Application\Archive\Service\ProductionLookup;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Rebit\Share\Application\Contract\File\Dto\TextFileOutputDto;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;

/**
 * PRD-07: состав версии в CSV из кабинета — тот же, что лежит в корне ZIP, и доступный после удаления файла архива
 * по сроку хранения (L1-DEC-04): состав версии хранится всегда.
 */
final readonly class ExportProductionVersionUseCase
{
    public function __construct(
        private ProductionAccessInterface $access,
        private ProductionLookup $lookup,
        private ArchiveRepositoryInterface $archives,
        private OriginalFilesInterface $originals,
        private ManifestWriterInterface $manifest,
    ) {}

    public function execute(int $actorId, string $versionId): TextFileOutputDto
    {
        $version = $this->lookup->version($this->access->scope($actorId), $versionId);
        $lines = $this->archives->lines($version->id);
        $extensions = [];
        foreach ($this->originals->originals(array_values(array_unique(array_map(static fn($line): string => $line->photoId, $lines)))) as $photoId => $original) {
            $extension = strtolower(pathinfo($original->relativePath, PATHINFO_EXTENSION));
            $extensions[$photoId] = '' === $extension ? 'jpg' : $extension;
        }

        return new TextFileOutputDto(
            filename: 'print-' . substr($version->groupPublicId, 0, 8) . '-v' . $version->number . '.csv',
            mimeType: 'text/csv; charset=utf-8',
            content: $this->manifest->csv($lines, $extensions),
        );
    }
}
