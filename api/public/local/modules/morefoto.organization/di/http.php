<?php

declare(strict_types=1);
use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Organization\Application\Institution\Contract\InstitutionTransactionInterface;
use Morefoto\Organization\Application\Institution\UseCase\SaveInstitutionUseCase;
use Morefoto\Organization\Application\Institution\UseCase\GetInstitutionDetailUseCase;
use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Morefoto\Organization\Application\Institution\UseCase\ListVisibleInstitutionsUseCase;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionRepository;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionOperationRepository;
use Morefoto\Organization\Infrastructure\Persistence\InstitutionTransaction;
use Morefoto\Organization\Presentation\Controller\InstitutionController;
use Morefoto\Organization\Presentation\Request\InstitutionRequestFactory;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;

return [
    InstitutionOperationRepository::class => ['className' => InstitutionOperationRepository::class],
    InstitutionRequestFactory::class => ['className' => InstitutionRequestFactory::class],
    InstitutionTransactionInterface::class => ['constructor' => static fn(): InstitutionTransactionInterface => new InstitutionTransaction()],
    SaveInstitutionUseCase::class => [
        'className' => SaveInstitutionUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(InstitutionRepository::class),
            ServiceLocator::getInstance()->get(InstitutionOperationRepository::class),
            ServiceLocator::getInstance()->get(InstitutionAccessInterface::class),
            ServiceLocator::getInstance()->get(InstitutionTransactionInterface::class),
        ],
    ],
    ListVisibleInstitutionsUseCase::class => [
        'className' => ListVisibleInstitutionsUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(InstitutionRepository::class),
            ServiceLocator::getInstance()->get(InstitutionAccessInterface::class),
            ServiceLocator::getInstance()->get(TokenResolverInterface::class),
            ServiceLocator::getInstance()->get(CalendarClockInterface::class),
        ],
    ],
    GetInstitutionDetailUseCase::class => [
        'className' => GetInstitutionDetailUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(InstitutionRepository::class),
            ServiceLocator::getInstance()->get(StructureRepository::class),
            ServiceLocator::getInstance()->get(InstitutionAccessInterface::class),
            ServiceLocator::getInstance()->get(GroupAccessInterface::class),
            ServiceLocator::getInstance()->get(TokenResolverInterface::class),
            ServiceLocator::getInstance()->get(CalendarClockInterface::class),
        ],
    ],
    InstitutionController::class => [
        'className' => InstitutionController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ListVisibleInstitutionsUseCase::class),
            ServiceLocator::getInstance()->get(SaveInstitutionUseCase::class),
            ServiceLocator::getInstance()->get(InstitutionRequestFactory::class),
            ServiceLocator::getInstance()->get(TokenResolverInterface::class),
            ServiceLocator::getInstance()->get(GetInstitutionDetailUseCase::class),
        ],
    ],
];
