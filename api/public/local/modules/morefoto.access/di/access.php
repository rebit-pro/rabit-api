<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Infrastructure\Adapter\AccessGuard;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Morefoto\Access\Application\Bootstrap\UseCase\BootstrapOrganizerUseCase;
use Morefoto\Access\Application\Profile\UseCase\GetProfileUseCase;
use Morefoto\Access\Domain\Staff\Repository\AccessStateRepository;
use Morefoto\Access\Domain\Staff\Repository\StaffProfileRepository;
use Morefoto\Access\Domain\Staff\Service\PermissionPolicy;
use Morefoto\Access\Presentation\Console\BootstrapOrganizerCommand;
use Morefoto\Access\Presentation\Controller\ProfileController;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Morefoto\Access\Domain\Assignment\Repository\InstitutionAssignmentRepository;
use Morefoto\Access\Application\Assignment\Service\InstitutionAccess;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Morefoto\Access\Domain\Assignment\Repository\GroupAssignmentRepository;
use Morefoto\Access\Application\Assignment\Service\GroupAccess;
use Rebit\Share\Contracts\Access\GroupAccessInterface;

return [
    GroupAssignmentRepository::class => ['className' => GroupAssignmentRepository::class],
    GroupAccessInterface::class => [
        'constructor' => static fn(): GroupAccessInterface => new GroupAccess(
            ServiceLocator::getInstance()->get(GroupAssignmentRepository::class),
            ServiceLocator::getInstance()->get(InstitutionAccessInterface::class),
            ServiceLocator::getInstance()->get(InstitutionAssignmentRepository::class),
            ServiceLocator::getInstance()->get(StaffProfileRepository::class),
            ServiceLocator::getInstance()->get(IdentityGatewayInterface::class),
        ),
    ],
    InstitutionAssignmentRepository::class => ['className' => InstitutionAssignmentRepository::class],
    InstitutionAccessInterface::class => [
        'constructor' => static fn(): InstitutionAccessInterface => new InstitutionAccess(
            ServiceLocator::getInstance()->get(InstitutionAssignmentRepository::class),
            ServiceLocator::getInstance()->get(StaffProfileRepository::class),
            ServiceLocator::getInstance()->get(StaffAuthorization::class),
            ServiceLocator::getInstance()->get(IdentityGatewayInterface::class),
            ServiceLocator::getInstance()->get(TokenResolverInterface::class),
        ),
    ],
    StaffProfileRepository::class => ['className' => StaffProfileRepository::class],
    AccessStateRepository::class => ['className' => AccessStateRepository::class],
    PermissionPolicy::class => ['className' => PermissionPolicy::class],
    StaffAuthorization::class => [
        'className' => StaffAuthorization::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(StaffProfileRepository::class),
            ServiceLocator::getInstance()->get(IdentityGatewayInterface::class),
            ServiceLocator::getInstance()->get(PermissionPolicy::class),
            ServiceLocator::getInstance()->get(InstitutionAssignmentRepository::class),
            ServiceLocator::getInstance()->get(GroupAssignmentRepository::class),
        ],
    ],
    AccessGuardInterface::class => [
        'constructor' => static fn(): AccessGuardInterface => new AccessGuard(ServiceLocator::getInstance()->get(StaffAuthorization::class)),
    ],
    GetProfileUseCase::class => [
        'className' => GetProfileUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(StaffAuthorization::class),
            ServiceLocator::getInstance()->get(PermissionPolicy::class),
        ],
    ],
    BootstrapOrganizerUseCase::class => [
        'className' => BootstrapOrganizerUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(AccessStateRepository::class),
            ServiceLocator::getInstance()->get(StaffProfileRepository::class),
            ServiceLocator::getInstance()->get(IdentityGatewayInterface::class),
        ],
    ],
    BootstrapOrganizerCommand::class => [
        'className' => BootstrapOrganizerCommand::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(BootstrapOrganizerUseCase::class),
            (string)(getenv('APP_ENV') ?: ''),
        ],
    ],
    ProfileController::class => [
        'className' => ProfileController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GetProfileUseCase::class),
            ServiceLocator::getInstance()->get(TokenResolverInterface::class),
        ],
    ],
];
