<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Production\Application\Archive\UseCase\CreateProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\ExportProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\GetGroupProductionUseCase;
use Morefoto\Production\Application\Archive\UseCase\GetProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\ListProductionGroupsUseCase;
use Morefoto\Production\Application\Archive\UseCase\OpenProductionArchiveUseCase;
use Morefoto\Production\Application\Archive\UseCase\RenewProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\RunProductionUseCase;
use Morefoto\Production\Presentation\Archive\ProductionInputMapper;
use Morefoto\Production\Presentation\Archive\ProductionResultMapper;
use Morefoto\Production\Presentation\Command\RunProductionCommand;
use Morefoto\Production\Presentation\Controller\ProductionController;
use Morefoto\Production\Presentation\Controller\PublicArchiveController;

return [
    ProductionInputMapper::class => ['className' => ProductionInputMapper::class],
    ProductionResultMapper::class => ['className' => ProductionResultMapper::class],
    ProductionController::class => [
        'className' => ProductionController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ListProductionGroupsUseCase::class),
            ServiceLocator::getInstance()->get(GetGroupProductionUseCase::class),
            ServiceLocator::getInstance()->get(CreateProductionVersionUseCase::class),
            ServiceLocator::getInstance()->get(GetProductionVersionUseCase::class),
            ServiceLocator::getInstance()->get(ExportProductionVersionUseCase::class),
            ServiceLocator::getInstance()->get(RenewProductionVersionUseCase::class),
            ServiceLocator::getInstance()->get(ProductionInputMapper::class),
            ServiceLocator::getInstance()->get(ProductionResultMapper::class),
        ],
    ],
    PublicArchiveController::class => [
        'className' => PublicArchiveController::class,
        'constructorParams' => static fn(): array => [ServiceLocator::getInstance()->get(OpenProductionArchiveUseCase::class)],
    ],
    RunProductionCommand::class => [
        'className' => RunProductionCommand::class,
        'constructorParams' => static fn(): array => [ServiceLocator::getInstance()->get(RunProductionUseCase::class)],
    ],
];
