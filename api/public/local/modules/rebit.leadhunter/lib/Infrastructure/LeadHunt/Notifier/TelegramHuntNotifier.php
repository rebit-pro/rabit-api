<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Infrastructure\LeadHunt\Notifier;

use Psr\Log\LoggerInterface;
use Rebit\Leadhunter\Application\LeadHunt\Dto\PendingLeadDto;
use Rebit\Leadhunter\Application\LeadHunt\Port\HuntNotifierInterface;
use Rebit\Share\Infrastructure\Telegram\TelegramBotApiClient;

/**
 * Доставка найденной на внешней площадке заявки в Telegram (sendMessage).
 *
 * Провал не бросает исключений: заявка остаётся в статусе failed
 * и ретраится следующими прогонами команды.
 */
final readonly class TelegramHuntNotifier implements HuntNotifierInterface
{
    private const int TIMEOUT_SECONDS = 12;

    private const int DESCRIPTION_LIMIT = 700;

    public function __construct(
        private LoggerInterface $logger,
        private TelegramBotApiClient $client,
        private string $chatId,
    ) {}

    public function notify(PendingLeadDto $lead): bool
    {
        if (!$this->client->isConfigured() || '' === $this->chatId) {
            $this->logger->error('Telegram-получатель внешних заявок не настроен: пустой токен или chat_id');

            return false;
        }

        return $this->client->call(
            'sendMessage',
            http_build_query([
                'chat_id' => $this->chatId,
                'text' => $this->buildText($lead),
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => 'true',
            ]),
            self::TIMEOUT_SECONDS,
        );
    }

    private function buildText(PendingLeadDto $lead): string
    {
        $lines = [
            '🎯 <b>Заявка с ' . $this->escape($lead->source->title()) . '</b>',
            '',
            '<b>' . $this->escape($lead->title) . '</b>',
        ];

        if ([] !== $lead->matchedKeywords) {
            $lines[] = '🔑 ' . $this->escape(implode(', ', $lead->matchedKeywords));
        }

        if ('' !== $lead->description) {
            $lines[] = '';
            $lines[] = $this->escape($this->truncate($lead->description));
        }

        if ('' !== $lead->url) {
            $lines[] = '';
            $lines[] = '🔗 ' . $this->escape($lead->url);
        }

        return implode("\n", $lines);
    }

    private function truncate(string $text): string
    {
        if (mb_strlen($text) <= self::DESCRIPTION_LIMIT) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, self::DESCRIPTION_LIMIT)) . '…';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
