<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Rebit\Auth\Application\Access\Contract\AccessAccountInterface;
use Rebit\Auth\Application\Access\Contract\AccessLinkMailerInterface;
use Rebit\Auth\Application\Access\Contract\AccessLinkRepositoryInterface;
use Rebit\Auth\Application\Access\Contract\AccessTokenGeneratorInterface;
use Rebit\Auth\Application\Access\Service\AccessLinkGuard;
use Rebit\Auth\Application\Access\Service\SessionIssuer;
use Rebit\Auth\Application\Access\UseCase\AcceptAccessInvitationUseCase;
use Rebit\Auth\Application\Access\UseCase\ChangePasswordUseCase;
use Rebit\Auth\Application\Access\UseCase\ConfirmPasswordResetUseCase;
use Rebit\Auth\Application\Access\UseCase\GetAccessInvitationUseCase;
use Rebit\Auth\Application\Access\UseCase\IssueAccessInvitationUseCase;
use Rebit\Auth\Application\Access\UseCase\RequestPasswordResetUseCase;
use Rebit\Auth\Application\Auth\Contract\AuthTransactionInterface;
use Rebit\Auth\Application\Auth\Contract\ClockInterface;
use Rebit\Auth\Application\Auth\Contract\LoginUserRepositoryInterface;
use Rebit\Auth\Application\Auth\Contract\TokenGeneratorInterface;
use Rebit\Auth\Domain\Access\Service\EmailMask;
use Rebit\Auth\Domain\Access\Service\PasswordPolicy;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Auth\Infrastructure\Access\H1AccessLinkMailer;
use Rebit\Auth\Infrastructure\Access\RandomAccessTokenGenerator;
use Rebit\Auth\Infrastructure\Access\SqlAccessLinkRepository;
use Rebit\Auth\Infrastructure\Access\UserAccessAccount;
use Rebit\Auth\Presentation\Access\AccessInputMapper;
use Rebit\Auth\Presentation\Controller\AccessLinkController;
use Rebit\Auth\Presentation\Controller\PasswordController;
use Rebit\Share\Application\Contract\Notification\EmailNotificationInterface;
use Rebit\Share\Application\Contract\Consent\ConsentRecorderInterface;
use Rebit\Share\Presentation\Consent\AcceptedDocumentInputMapper;

$locator = static fn(): ServiceLocator => ServiceLocator::getInstance();
$cooldown = static fn(): int => (int)(getenv('REBIT_AUTH_LINK_COOLDOWN_SECONDS') ?: 60);

return [
    AccessLinkRepositoryInterface::class => [
        'constructor' => static fn(): AccessLinkRepositoryInterface => new SqlAccessLinkRepository(),
    ],
    AccessAccountInterface::class => [
        'constructor' => static fn(): AccessAccountInterface => new UserAccessAccount($locator()->get(UserRepository::class)),
    ],
    AccessTokenGeneratorInterface::class => [
        'constructor' => static fn(): AccessTokenGeneratorInterface => new RandomAccessTokenGenerator(),
    ],
    AccessLinkMailerInterface::class => [
        'constructor' => static fn(): AccessLinkMailerInterface => new H1AccessLinkMailer(
            $locator()->get(EmailNotificationInterface::class),
            (string)(getenv('REBIT_AUTH_APP_URL') ?: 'https://app.morefoto36.ru'),
            (string)(getenv('REBIT_AUTH_BRAND_NAME') ?: 'Море фото'),
        ),
    ],
    PasswordPolicy::class => ['className' => PasswordPolicy::class],
    EmailMask::class => ['className' => EmailMask::class],
    AccessLinkGuard::class => ['className' => AccessLinkGuard::class],
    AccessInputMapper::class => [
        'className' => AccessInputMapper::class,
        'constructorParams' => static fn(): array => [$locator()->get(AcceptedDocumentInputMapper::class)],
    ],
    SessionIssuer::class => [
        'className' => SessionIssuer::class,
        'constructorParams' => static fn(): array => [
            $locator()->get(TokenGeneratorInterface::class),
            $locator()->get(LoginUserRepositoryInterface::class),
            $locator()->get(ClockInterface::class),
            (int)(getenv('REBIT_TOKEN_TTL_HOURS') ?: 24),
        ],
    ],
    IssueAccessInvitationUseCase::class => [
        'className' => IssueAccessInvitationUseCase::class,
        'constructorParams' => static fn(): array => [
            $locator()->get(AccessAccountInterface::class),
            $locator()->get(AccessLinkRepositoryInterface::class),
            $locator()->get(AccessTokenGeneratorInterface::class),
            $locator()->get(AccessLinkMailerInterface::class),
            $locator()->get(ClockInterface::class),
            (int)(getenv('REBIT_AUTH_INVITE_TTL_HOURS') ?: 168),
            $cooldown(),
        ],
    ],
    GetAccessInvitationUseCase::class => [
        'className' => GetAccessInvitationUseCase::class,
        'constructorParams' => static fn(): array => [
            $locator()->get(AccessLinkRepositoryInterface::class),
            $locator()->get(AccessAccountInterface::class),
            $locator()->get(AccessLinkGuard::class),
            $locator()->get(EmailMask::class),
            $locator()->get(ClockInterface::class),
        ],
    ],
    AcceptAccessInvitationUseCase::class => [
        'className' => AcceptAccessInvitationUseCase::class,
        'constructorParams' => static fn(): array => [
            $locator()->get(AccessLinkRepositoryInterface::class),
            $locator()->get(AccessAccountInterface::class),
            $locator()->get(AccessLinkGuard::class),
            $locator()->get(PasswordPolicy::class),
            $locator()->get(SessionIssuer::class),
            $locator()->get(ClockInterface::class),
            $locator()->get(AuthTransactionInterface::class),
            $locator()->get(ConsentRecorderInterface::class),
        ],
    ],
    RequestPasswordResetUseCase::class => [
        'className' => RequestPasswordResetUseCase::class,
        'constructorParams' => static fn(): array => [
            $locator()->get(AccessAccountInterface::class),
            $locator()->get(AccessLinkRepositoryInterface::class),
            $locator()->get(AccessTokenGeneratorInterface::class),
            $locator()->get(AccessLinkMailerInterface::class),
            $locator()->get(IssueAccessInvitationUseCase::class),
            $locator()->get(ClockInterface::class),
            $locator()->get(AuthTransactionInterface::class),
            (int)(getenv('REBIT_AUTH_RESET_TTL_MINUTES') ?: 60),
            $cooldown(),
        ],
    ],
    ConfirmPasswordResetUseCase::class => [
        'className' => ConfirmPasswordResetUseCase::class,
        'constructorParams' => static fn(): array => [
            $locator()->get(AccessLinkRepositoryInterface::class),
            $locator()->get(AccessAccountInterface::class),
            $locator()->get(AccessLinkGuard::class),
            $locator()->get(PasswordPolicy::class),
            $locator()->get(SessionIssuer::class),
            $locator()->get(ClockInterface::class),
            $locator()->get(AuthTransactionInterface::class),
        ],
    ],
    ChangePasswordUseCase::class => [
        'className' => ChangePasswordUseCase::class,
        'constructorParams' => static fn(): array => [
            $locator()->get(AccessAccountInterface::class),
            $locator()->get(PasswordPolicy::class),
            $locator()->get(AuthTransactionInterface::class),
        ],
    ],
    AccessLinkController::class => [
        'className' => AccessLinkController::class,
        'constructorParams' => static fn(): array => [
            $locator()->get(GetAccessInvitationUseCase::class),
            $locator()->get(AcceptAccessInvitationUseCase::class),
            $locator()->get(RequestPasswordResetUseCase::class),
            $locator()->get(ConfirmPasswordResetUseCase::class),
            $locator()->get(AccessInputMapper::class),
        ],
    ],
    PasswordController::class => [
        'className' => PasswordController::class,
        'constructorParams' => static fn(): array => [
            $locator()->get(ChangePasswordUseCase::class),
            $locator()->get(AccessInputMapper::class),
        ],
    ],
];
