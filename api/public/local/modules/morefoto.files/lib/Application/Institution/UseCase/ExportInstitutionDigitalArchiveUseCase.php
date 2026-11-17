<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\UseCase;

use Morefoto\Files\Application\Institution\Contract\DigitalArchiveWriterInterface;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveAccess;
use Rebit\Share\Application\Contract\File\Dto\TextFileOutputDto;

/** Выдаёт сохраняемый манифест покупки и файлов независимо от срока временного ZIP, только уполномоченному сотруднику. */
final readonly class ExportInstitutionDigitalArchiveUseCase
{
    public function __construct(private DigitalArchiveAccess $access, private DigitalArchiveWriterInterface $writer) {}

    public function execute(int $actorId, string $archiveId): TextFileOutputDto
    {
        $archive = $this->access->archive($actorId, $archiveId);

        return new TextFileOutputDto('institution-digital-' . $archive->id . '.csv', 'text/csv; charset=utf-8', $this->writer->manifest($archive));
    }
}
