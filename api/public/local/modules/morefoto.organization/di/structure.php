<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Organization\Application\Institution\Contract\InstitutionTransactionInterface;
use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Morefoto\Organization\Application\Structure\Service\GroupReference;
use Morefoto\Organization\Application\Structure\UseCase\ListShootsUseCase;
use Morefoto\Organization\Application\Structure\UseCase\GetShootUseCase;
use Morefoto\Organization\Application\Structure\UseCase\SaveShootUseCase;
use Morefoto\Organization\Application\Structure\UseCase\SaveGroupUseCase;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionOperationRepository;
use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Morefoto\Organization\Infrastructure\Routing\StructureRouteParameters;
use Morefoto\Organization\Presentation\Controller\StructureController;
use Morefoto\Organization\Presentation\Request\StructureRequestFactory;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Contracts\Organization\GroupReferenceInterface;

$services = [
    StructureRepository::class => ['className' => StructureRepository::class],
    StructureRequestFactory::class => ['className' => StructureRequestFactory::class],
    StructureRouteParameters::class => ['className' => StructureRouteParameters::class],
    GroupReferenceInterface::class => [
        'constructor' => static fn(): GroupReferenceInterface => new GroupReference(ServiceLocator::getInstance()->get(StructureRepository::class)),
    ],
];
$dependencies = [
    ListShootsUseCase::class => [StructureRepository::class, InstitutionAccessInterface::class, TokenResolverInterface::class],
    GetShootUseCase::class => [StructureRepository::class, InstitutionAccessInterface::class, GroupAccessInterface::class, TokenResolverInterface::class, CalendarClockInterface::class],
    SaveShootUseCase::class => [StructureRepository::class, InstitutionOperationRepository::class, InstitutionAccessInterface::class, InstitutionTransactionInterface::class],
    SaveGroupUseCase::class => [StructureRepository::class, InstitutionOperationRepository::class, InstitutionAccessInterface::class, GroupAccessInterface::class, InstitutionTransactionInterface::class],
    StructureController::class => [ListShootsUseCase::class, GetShootUseCase::class, SaveShootUseCase::class, SaveGroupUseCase::class, StructureRequestFactory::class, StructureRouteParameters::class, TokenResolverInterface::class],
];
foreach ($dependencies as $class => $arguments) {
    $services[$class] = [
        'className' => $class,
        'constructorParams' => static fn(): array => array_map(static fn(string $dependency): object => ServiceLocator::getInstance()->get($dependency), $arguments),
    ];
}

return $services;
