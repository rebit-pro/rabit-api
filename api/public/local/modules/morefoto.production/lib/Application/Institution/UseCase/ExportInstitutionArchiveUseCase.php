<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Institution\UseCase;

use Morefoto\Production\Application\Archive\Contract\ManifestWriterInterface;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveAccess;
use Morefoto\Production\Application\Institution\Service\InstitutionSnapshot;
use Rebit\Share\Application\Contract\File\Dto\TextFileOutputDto;

/** Возвращает точный CSV сохранённого выпуска учреждения даже после истечения срока ZIP, не меняя его состав. */
final readonly class ExportInstitutionArchiveUseCase
{
    public function __construct(private InstitutionArchiveAccess $access, private InstitutionSnapshot $snapshot, private ManifestWriterInterface $manifest) {}

    public function execute(int $actorId, string $archiveId): TextFileOutputDto
    {
        $archive = $this->access->archive($actorId, $archiveId);

        return new TextFileOutputDto('institution-print-v' . $archive->number . '.csv', 'text/csv; charset=utf-8', $this->manifest->csv($this->snapshot->lines($archive), [], 2));
    }
}
