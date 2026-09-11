<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Rebit\Auth\Application\Auth\Contract\CaptchaVerifierInterface;
use Rebit\Auth\Application\Auth\Contract\LoginUserRepositoryInterface;
use Rebit\Auth\Application\Auth\Contract\RegistrationConfirmationMailerInterface;
use Rebit\Auth\Application\Auth\Contract\TokenGeneratorInterface;
use Rebit\Auth\Application\Auth\UseCase\ConfirmRegistrationUseCase;
use Rebit\Auth\Infrastructure\Adapter\BitrixMailEventRegistrationConfirmationMailer;
use Rebit\Auth\Infrastructure\Adapter\TokenResolver;
use Rebit\Auth\Infrastructure\Adapter\GeeTestCaptchaVerifier;
use Rebit\Auth\Application\Auth\UseCase\LoginUseCase;
use Rebit\Auth\Application\Auth\UseCase\LogoutUseCase;
use Rebit\Auth\Application\Auth\UseCase\RequestRegistrationCodeUseCase;
use Rebit\Auth\Domain\Registration\Repository\RegistrationConfirmationRepository;
use Rebit\Auth\Domain\Registration\Service\RegistrationCodeGenerator;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Auth\Infrastructure\Adapter\TokenGenerator;
use Rebit\Auth\Presentation\Controller\AuthController;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Infrastructure\HttpClient\RebitHttpClientFactory;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Facade\Log;

return [
    UserRepository::class => [
        'className' => UserRepository::class,
    ],
    LoginUserRepositoryInterface::class => [
        'constructor' => static function(): LoginUserRepositoryInterface {
            return ServiceLocator::getInstance()->get(UserRepository::class);
        },
    ],
    TokenGenerator::class => [
        'className' => TokenGenerator::class,
    ],
    RegistrationConfirmationRepository::class => [
        'className' => RegistrationConfirmationRepository::class,
    ],
    RegistrationCodeGenerator::class => [
        'className' => RegistrationCodeGenerator::class,
    ],
    TokenGeneratorInterface::class => [
        'constructor' => static function(): TokenGeneratorInterface {
            return ServiceLocator::getInstance()->get(TokenGenerator::class);
        },
    ],
    RegistrationConfirmationMailerInterface::class => [
        'constructor' => static function(): RegistrationConfirmationMailerInterface {
            return new BitrixMailEventRegistrationConfirmationMailer(
                siteId: (string)(getenv('REBIT_AUTH_MAIL_EVENT_SITE_ID') ?: 's1'),
            );
        },
    ],
    CaptchaVerifierInterface::class => [
        'constructor' => static function(): CaptchaVerifierInterface {
            $logger = Log::channel(LogChannelEnum::auth);

            return new GeeTestCaptchaVerifier(
                RebitHttpClientFactory::create($logger),
                $logger,
                (string)(getenv('REBIT_GEETEST_CAPTCHA_ID') ?: ''),
                (string)(getenv('REBIT_GEETEST_CAPTCHA_KEY') ?: ''),
                filter_var(getenv('REBIT_GEETEST_ENABLED') ?: '0', FILTER_VALIDATE_BOOL),
                filter_var(getenv('REBIT_GEETEST_BYPASS') ?: '0', FILTER_VALIDATE_BOOL),
                (string)(getenv('REBIT_GEETEST_API_URL') ?: 'https://gcaptcha4.geetest.com'),
            );
        },
    ],
    TokenResolverInterface::class => [
        'constructor' => static function(): TokenResolverInterface {
            return new TokenResolver(
                ServiceLocator::getInstance()->get(UserRepository::class),
            );
        },
    ],
    LoginUseCase::class => [
        'className' => LoginUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(LoginUserRepositoryInterface::class),
            ServiceLocator::getInstance()->get(TokenGeneratorInterface::class),
            ServiceLocator::getInstance()->get(CaptchaVerifierInterface::class),
            (int)(getenv('REBIT_TOKEN_TTL_HOURS') ?: 24),
        ],
    ],
    LogoutUseCase::class => [
        'className' => LogoutUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(UserRepository::class),
        ],
    ],
    RequestRegistrationCodeUseCase::class => [
        'className' => RequestRegistrationCodeUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(UserRepository::class),
            ServiceLocator::getInstance()->get(RegistrationConfirmationRepository::class),
            ServiceLocator::getInstance()->get(RegistrationCodeGenerator::class),
            ServiceLocator::getInstance()->get(RegistrationConfirmationMailerInterface::class),
            (int)(getenv('REBIT_AUTH_REGISTRATION_CODE_TTL_MINUTES') ?: 15),
            (int)(getenv('REBIT_AUTH_REGISTRATION_RESEND_COOLDOWN_SECONDS') ?: 60),
        ],
    ],
    ConfirmRegistrationUseCase::class => [
        'className' => ConfirmRegistrationUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(UserRepository::class),
            ServiceLocator::getInstance()->get(RegistrationConfirmationRepository::class),
            ServiceLocator::getInstance()->get(TokenGeneratorInterface::class),
            (int)(getenv('REBIT_TOKEN_TTL_HOURS') ?: 24),
            (int)(getenv('REBIT_AUTH_REGISTRATION_MAX_ATTEMPTS') ?: 5),
        ],
    ],
    AuthController::class => [
        'className' => AuthController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(LoginUseCase::class),
            ServiceLocator::getInstance()->get(LogoutUseCase::class),
            ServiceLocator::getInstance()->get(RequestRegistrationCodeUseCase::class),
            ServiceLocator::getInstance()->get(ConfirmRegistrationUseCase::class),
            ServiceLocator::getInstance()->get(TokenResolverInterface::class),
        ],
    ],
];
