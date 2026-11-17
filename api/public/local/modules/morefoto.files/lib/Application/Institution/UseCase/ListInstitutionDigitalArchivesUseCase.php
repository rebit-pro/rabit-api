<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\UseCase;

use Morefoto\Files\Application\Institution\Dto\DigitalArchiveListOutputDto;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveAccess;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveView;
use Morefoto\Files\Domain\Institution\Repository\DigitalArchiveRepositoryInterface;

/** Возвращает последние двадцать сохранённых выгрузок учреждения в текущей области доступа сотрудника. */
final readonly class ListInstitutionDigitalArchivesUseCase
{
    public function __construct(private DigitalArchiveAccess $access, private DigitalArchiveRepositoryInterface $archives, private DigitalArchiveView $view) {}

    public function execute(int $actorId, string $institutionId): DigitalArchiveListOutputDto
    {
        $institution = $this->access->institution($actorId, $institutionId);

        return new DigitalArchiveListOutputDto($this->access->canCreate($actorId), array_map($this->view->summary(...), $this->archives->latest($institution->nativeId)));
    }
}
