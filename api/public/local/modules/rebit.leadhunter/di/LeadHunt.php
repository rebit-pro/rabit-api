<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Rebit\Leadhunter\Application\LeadHunt\Port\ExternalLeadRepositoryInterface;
use Rebit\Leadhunter\Application\LeadHunt\Port\HuntNotifierInterface;
use Rebit\Leadhunter\Application\LeadHunt\Port\HuntRuleProviderInterface;
use Rebit\Leadhunter\Application\LeadHunt\Service\LeadFeedRegistry;
use Rebit\Leadhunter\Application\LeadHunt\UseCase\ScanLeadsUseCase;
use Rebit\Leadhunter\Domain\LeadHunt\Enum\LeadSourceEnum;
use Rebit\Leadhunter\Domain\LeadHunt\Service\KeywordMatcher;
use Rebit\Leadhunter\Infrastructure\LeadHunt\Config\EnvHuntRuleProvider;
use Rebit\Leadhunter\Infrastructure\LeadHunt\Feed\FlRuRssFeed;
use Rebit\Leadhunter\Infrastructure\LeadHunt\Notifier\EmailHuntNotifier;
use Rebit\Leadhunter\Infrastructure\LeadHunt\Notifier\FallbackHuntNotifier;
use Rebit\Leadhunter\Infrastructure\LeadHunt\Notifier\TelegramHuntNotifier;
use Rebit\Leadhunter\Infrastructure\LeadHunt\Repository\BitrixExternalLeadRepository;
use Rebit\Leadhunter\Presentation\Command\LeadHunt\ScanLeadsCommand;
use Rebit\Share\Infrastructure\Telegram\TelegramBotApiClient;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Facade\Log;

return [
    HuntRuleProviderInterface::class => [
        'constructor' => static function(): HuntRuleProviderInterface {
            return new EnvHuntRuleProvider(
                Log::channel(LogChannelEnum::leadhunter),
                (string)(getenv('REBIT_LEADHUNTER_RULES') ?: ''),
            );
        },
    ],

    KeywordMatcher::class => [
        'className' => KeywordMatcher::class,
    ],

    LeadFeedRegistry::class => [
        'constructor' => static function(): LeadFeedRegistry {
            return new LeadFeedRegistry([
                LeadSourceEnum::FL_RU->value => new FlRuRssFeed(
                    Log::channel(LogChannelEnum::leadhunter),
                    (string)(getenv('REBIT_LEADHUNTER_FLRU_RSS_URL') ?: 'https://www.fl.ru/rss/all.xml'),
                ),
            ]);
        },
    ],

    ExternalLeadRepositoryInterface::class => [
        'className' => BitrixExternalLeadRepository::class,
    ],

    HuntNotifierInterface::class => [
        'constructor' => static function(): HuntNotifierInterface {
            // Чат общий с заявками с сайта; свой chat_id — через REBIT_LEADHUNTER_TELEGRAM_CHAT_ID.
            $telegram = new TelegramHuntNotifier(
                Log::channel(LogChannelEnum::leadhunter),
                ServiceLocator::getInstance()->get(TelegramBotApiClient::class),
                (string)(getenv('REBIT_LEADHUNTER_TELEGRAM_CHAT_ID') ?: getenv('REBIT_NOTIFICATION_TELEGRAM_CHAT_ID') ?: ''),
            );

            $fallbackEmail = (string)(getenv('REBIT_LEADHUNTER_FALLBACK_EMAIL') ?: '');

            if ('' === $fallbackEmail) {
                return $telegram;
            }

            return new FallbackHuntNotifier(
                Log::channel(LogChannelEnum::leadhunter),
                $telegram,
                new EmailHuntNotifier(
                    Log::channel(LogChannelEnum::leadhunter),
                    $fallbackEmail,
                    (string)(getenv('REBIT_AUTH_MAIL_EVENT_SITE_ID') ?: 's1'),
                ),
            );
        },
    ],

    ScanLeadsUseCase::class => [
        'className' => ScanLeadsUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(HuntRuleProviderInterface::class),
            ServiceLocator::getInstance()->get(LeadFeedRegistry::class),
            ServiceLocator::getInstance()->get(KeywordMatcher::class),
            ServiceLocator::getInstance()->get(ExternalLeadRepositoryInterface::class),
            ServiceLocator::getInstance()->get(HuntNotifierInterface::class),
            Log::channel(LogChannelEnum::leadhunter),
        ],
    ],

    ScanLeadsCommand::class => [
        'className' => ScanLeadsCommand::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ScanLeadsUseCase::class),
        ],
    ],
];
