<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\UseCase;

use Morefoto\Production\Application\Archive\Contract\ProductionAccessInterface;
use Morefoto\Production\Application\Archive\Dto\VersionOutputDto;
use Morefoto\Production\Application\Archive\Service\ProductionLookup;
use Morefoto\Production\Application\Archive\Service\VersionView;
use Rebit\Share\Application\Contract\Clock\ClockInterface;

/** PRD-04: одна версия архива печати в области сотрудника — для ожидания сборки после кнопки организатора. */
final readonly class GetProductionVersionUseCase
{
    public function __construct(
        private ProductionAccessInterface $access,
        private ProductionLookup $lookup,
        private VersionView $view,
        private ClockInterface $clock,
    ) {}

    public function execute(int $actorId, string $versionId): VersionOutputDto
    {
        return $this->view->one($this->lookup->version($this->access->scope($actorId), $versionId), $this->clock->now());
    }
}
