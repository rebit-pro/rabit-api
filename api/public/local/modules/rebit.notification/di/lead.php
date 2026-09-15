<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Rebit\Notification\Application\Lead\Port\LeadNotifierInterface;
use Rebit\Notification\Application\Lead\UseCase\SubmitLeadUseCase;
use Rebit\Notification\Infrastructure\Lead\EmailLeadNotifier;
use Rebit\Notification\Infrastructure\Lead\FallbackLeadNotifier;
use Rebit\Notification\Infrastructure\Lead\TelegramLeadNotifier;
use Rebit\Notification\Infrastructure\Lead\UploadedFileValidator;
use Rebit\Notification\Presentation\Controller\LeadController;
use Rebit\Share\Infrastructure\Telegram\TelegramBotApiClient;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Facade\Log;

// Лимит размера файла ТЗ (МБ). Дефолт 15; согласован с PHP upload_max_filesize.
$leadMaxFileMb = (int)(getenv('REBIT_NOTIFICATION_LEAD_MAX_FILE_MB') ?: 15);
if ($leadMaxFileMb <= 0) {
    $leadMaxFileMb = 15;
}
$leadMailSiteId = (string)(getenv('REBIT_AUTH_MAIL_EVENT_SITE_ID') ?: 's1');
$mosDizelLeadEmail = (string)(getenv('REBIT_NOTIFICATION_MOS_DIZEL_EMAIL') ?: '');
$mosDizelLeadEventName = 'REBIT_NOTIFICATION_MOS_DIZEL_LEAD';

return [
    LeadNotifierInterface::class => [
        'constructor' => static function() use ($leadMailSiteId): LeadNotifierInterface {
            $telegram = new TelegramLeadNotifier(
                Log::channel(LogChannelEnum::notification),
                ServiceLocator::getInstance()->get(TelegramBotApiClient::class),
                (string)(getenv('REBIT_NOTIFICATION_TELEGRAM_CHAT_ID') ?: ''),
            );

            // Общий с leadhunter ящик получателя, если свой не задан.
            $fallbackEmail = (string)(
                getenv('REBIT_NOTIFICATION_LEAD_FALLBACK_EMAIL')
                ?: getenv('REBIT_LEADHUNTER_FALLBACK_EMAIL')
                ?: ''
            );

            if ('' === $fallbackEmail) {
                return $telegram;
            }

            return new FallbackLeadNotifier(
                Log::channel(LogChannelEnum::notification),
                $telegram,
                new EmailLeadNotifier(
                    Log::channel(LogChannelEnum::notification),
                    $fallbackEmail,
                    $leadMailSiteId,
                ),
            );
        },
    ],

    UploadedFileValidator::class => [
        'constructor' => static function() use ($leadMaxFileMb): UploadedFileValidator {
            return new UploadedFileValidator($leadMaxFileMb * 1024 * 1024);
        },
    ],

    SubmitLeadUseCase::class => [
        'className' => SubmitLeadUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(LeadNotifierInterface::class),
        ],
    ],

    LeadController::class => [
        'constructor' => static function() use ($leadMailSiteId, $mosDizelLeadEmail, $mosDizelLeadEventName): LeadController {
            return new LeadController(
                ServiceLocator::getInstance()->get(SubmitLeadUseCase::class),
                new SubmitLeadUseCase(
                    new EmailLeadNotifier(
                        Log::channel(LogChannelEnum::notification),
                        $mosDizelLeadEmail,
                        $leadMailSiteId,
                        $mosDizelLeadEventName,
                    ),
                ),
                ServiceLocator::getInstance()->get(UploadedFileValidator::class),
            );
        },
    ],
];
