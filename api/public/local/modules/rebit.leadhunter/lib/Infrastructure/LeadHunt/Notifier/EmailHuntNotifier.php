<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Infrastructure\LeadHunt\Notifier;

use Psr\Log\LoggerInterface;
use Rebit\Leadhunter\Application\LeadHunt\Dto\PendingLeadDto;
use Rebit\Leadhunter\Application\LeadHunt\Port\HuntNotifierInterface;

/**
 * Доставка заявки письмом через почтовое событие Bitrix.
 *
 * Резервный канал на случай недоступности Telegram (блокировка, смерть прокси).
 * Провал не бросает исключений — заявка остаётся в статусе failed.
 */
final readonly class EmailHuntNotifier implements HuntNotifierInterface
{
    public const string EVENT_NAME = 'REBIT_LEADHUNTER_LEAD';

    public function __construct(
        private LoggerInterface $logger,
        private string $email,
        private string $siteId,
    ) {}

    public function notify(PendingLeadDto $lead): bool
    {
        if ('' === $this->email) {
            $this->logger->error('Email-получатель внешних заявок не настроен: пустой REBIT_LEADHUNTER_FALLBACK_EMAIL');

            return false;
        }

        $result = \CEvent::SendImmediate(self::EVENT_NAME, $this->siteId, [
            'EMAIL_TO' => $this->email,
            'SOURCE' => $this->escape($lead->source->title()),
            'TITLE' => $this->escape($lead->title),
            'DESCRIPTION' => nl2br($this->escape($lead->description)),
            'URL' => $this->escape($lead->url),
            'KEYWORDS' => $this->escape(implode(', ', $lead->matchedKeywords)),
        ]);

        if ('Y' !== $result) {
            $this->logger->error('Не удалось отправить заявку письмом', [
                'leadId' => $lead->id,
                'result' => (string)$result,
            ]);

            return false;
        }

        return true;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
