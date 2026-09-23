<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Access\Application\Authorization\Service\GroupLinkAccess;
use Morefoto\Access\Application\Authorization\Service\StaffRequestAccess;
use Morefoto\Access\Application\Staff\Contract\AssignmentDirectoryInterface;
use Morefoto\Access\Application\Staff\UseCase\SaveStaffUseCase;
use Morefoto\Access\Application\Staff\UseCase\StaffDirectoryUseCase;
use Morefoto\Access\Domain\Staff\Repository\StaffManagementRepository;
use Morefoto\Access\Infrastructure\Organization\OrganizationAssignmentDirectory;
use Morefoto\Access\Presentation\Controller\StaffController;
use Morefoto\Access\Presentation\Request\StaffRequestFactory;
use Rebit\Share\Application\Contract\Auth\StaffIdentityGatewayInterface;
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
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Contracts\Access\StaffRequestAccessInterface;
use Morefoto\Access\Presentation\Staff\StaffInvitationInputMapper;
use Morefoto\Access\Presentation\Controller\StaffInvitationController;
use Morefoto\Access\Application\Staff\UseCase\ResendStaffInvitationUseCase;

return [
    StaffRequestAccessInterface::class => ['constructor' => static fn(): StaffRequestAccessInterface => new StaffRequestAccess(ServiceLocator::getInstance()->get(StaffAuthorization::class))],
    GroupLinkAccessInterface::class => [
        'constructor' => static fn(): GroupLinkAccessInterface => new GroupLinkAccess(
            ServiceLocator::getInstance()->get(StaffAuthorization::class),
            ServiceLocator::getInstance()->get(InstitutionAssignmentRepository::class),
            ServiceLocator::getInstance()->get(IdentityGatewayInterface::class),
        ),
    ],
    AssignmentDirectoryInterface::class => [
        'constructor' => static fn(): AssignmentDirectoryInterface => new OrganizationAssignmentDirectory(),
    ],
    StaffManagementRepository::class => ['className' => StaffManagementRepository::class],
    StaffRequestFactory::class => ['className' => StaffRequestFactory::class],
    StaffDirectoryUseCase::class => [
        'className' => StaffDirectoryUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(StaffAuthorization::class),
            ServiceLocator::getInstance()->get(StaffManagementRepository::class),
            ServiceLocator::getInstance()->get(AssignmentDirectoryInterface::class),
            ServiceLocator::getInstance()->get(InstitutionAssignmentRepository::class),
            ServiceLocator::getInstance()->get(GroupAssignmentRepository::class),
            ServiceLocator::getInstance()->get(InstitutionAccessInterface::class),
            ServiceLocator::getInstance()->get(StaffIdentityGatewayInterface::class),
        ],
    ],
    SaveStaffUseCase::class => [
        'className' => SaveStaffUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(StaffAuthorization::class),
            ServiceLocator::getInstance()->get(AccessStateRepository::class),
            ServiceLocator::getInstance()->get(StaffManagementRepository::class),
            ServiceLocator::getInstance()->get(StaffIdentityGatewayInterface::class),
            ServiceLocator::getInstance()->get(AssignmentDirectoryInterface::class),
            ServiceLocator::getInstance()->get(InstitutionAssignmentRepository::class),
            ServiceLocator::getInstance()->get(GroupAssignmentRepository::class),
            ServiceLocator::getInstance()->get(InstitutionAccessInterface::class),
        ],
    ],
    ResendStaffInvitationUseCase::class => [
        'className' => ResendStaffInvitationUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(StaffAuthorization::class),
            ServiceLocator::getInstance()->get(AccessStateRepository::class),
            ServiceLocator::getInstance()->get(StaffManagementRepository::class),
            ServiceLocator::getInstance()->get(StaffIdentityGatewayInterface::class),
        ],
    ],
    StaffInvitationInputMapper::class => ['className' => StaffInvitationInputMapper::class],
    StaffInvitationController::class => [
        'className' => StaffInvitationController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ResendStaffInvitationUseCase::class),
            ServiceLocator::getInstance()->get(StaffInvitationInputMapper::class),
        ],
    ],
    StaffController::class => [
        'className' => StaffController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(StaffDirectoryUseCase::class),
            ServiceLocator::getInstance()->get(SaveStaffUseCase::class),
            ServiceLocator::getInstance()->get(StaffRequestFactory::class),
            ServiceLocator::getInstance()->get(TokenResolverInterface::class),
        ],
    ],
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
