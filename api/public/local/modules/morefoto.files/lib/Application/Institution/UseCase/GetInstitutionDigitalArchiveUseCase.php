<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\UseCase;

use Morefoto\Files\Application\Institution\Dto\DigitalArchiveOutputDto;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveAccess;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveView;

/** Читает состояние одной сохранённой выгрузки с повторной проверкой текущего доступа к учреждению. */
final readonly class GetInstitutionDigitalArchiveUseCase
{
    public function __construct(private DigitalArchiveAccess $access, private DigitalArchiveView $view) {}

    public function execute(int $actorId, string $archiveId): DigitalArchiveOutputDto
    {
        return $this->view->describe($this->access->archive($actorId, $archiveId));
    }
}
