<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Archive\Contract\ProductionAccessInterface;
use Morefoto\Production\Application\Archive\Service\ProductionLookup;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveAccess;
use Morefoto\Production\Application\Institution\Service\InstitutionSnapshot;
use Morefoto\Production\Application\Worksheet\Service\PrintComposition;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Institution\Repository\InstitutionArchiveRepositoryInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Production\PrintCompositionInterface;

return [
    PrintCompositionInterface::class => [
        'constructor' => static fn(): PrintCompositionInterface => new PrintComposition(
            ServiceLocator::getInstance()->get(ProductionAccessInterface::class),
            ServiceLocator::getInstance()->get(ProductionLookup::class),
            ServiceLocator::getInstance()->get(ArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(InstitutionArchiveAccess::class),
            ServiceLocator::getInstance()->get(InstitutionArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(InstitutionSnapshot::class),
            ServiceLocator::getInstance()->get(ArchiveStorageInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ),
    ],
];
