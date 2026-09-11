<?php

declare(strict_types=1);

namespace Rebit\Notification\Infrastructure\Lead;

use Psr\Log\LoggerInterface;
use Rebit\Notification\Application\Lead\Dto\LeadAttachmentDto;
use Rebit\Notification\Application\Lead\Dto\LeadMessageDto;
use Rebit\Notification\Application\Lead\Port\LeadNotifierInterface;
use Rebit\Share\Infrastructure\Telegram\TelegramBotApiClient;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Доставка заявки в Telegram через Bot API (sendMessage + sendDocument).
 *
 * Порядок доставки: сначала текст заявки (быстро, критично), затем — файл ТЗ
 * (медленнее). Если текст ушёл, а файл нет, запрос не валим: заявка уже принята,
 * ошибку вложения логируем и отдельным сообщением предупреждаем получателя.
 *
 * chat_id берётся из окружения сервера; транспорт (токен, прокси) — в TelegramBotApiClient.
 */
final readonly class TelegramLeadNotifier implements LeadNotifierInterface
{
    private const int TIMEOUT_SECONDS = 12;

    private const int DOCUMENT_TIMEOUT_SECONDS = 60;

    public function __construct(
        private LoggerInterface $logger,
        private TelegramBotApiClient $client,
        private string $chatId,
    ) {}

    /**
     * @throws HttpException
     */
    public function notify(LeadMessageDto $lead, ?LeadAttachmentDto $attachment = null): void
    {
        if (!$this->client->isConfigured() || '' === $this->chatId) {
            $this->logger->error('Telegram-получатель заявок не настроен: пустой токен или chat_id');

            throw new HttpException('Сервис заявок временно недоступен', 503);
        }

        // 1. Текст заявки — критичная часть: при провале возвращаем ошибку.
        $textSent = $this->client->call(
            'sendMessage',
            http_build_query([
                'chat_id' => $this->chatId,
                'text' => $this->buildText($lead),
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => 'true',
            ]),
            self::TIMEOUT_SECONDS,
        );

        if (!$textSent) {
            throw new HttpException('Не удалось отправить заявку', 502);
        }

        if (null === $attachment) {
            return;
        }

        // 2. Файл ТЗ — best-effort: заявка уже доставлена, провал файла не валит запрос.
        $documentSent = $this->client->call(
            'sendDocument',
            [
                'chat_id' => $this->chatId,
                'caption' => $this->buildCaption($lead),
                'parse_mode' => 'HTML',
                'document' => new \CURLFile($attachment->path, $attachment->mimeType, $attachment->name),
            ],
            self::DOCUMENT_TIMEOUT_SECONDS,
        );

        if (!$documentSent) {
            // Текст уже у получателя — предупреждаем его, что файл не дошёл (тоже best-effort).
            $this->client->call(
                'sendMessage',
                http_build_query([
                    'chat_id' => $this->chatId,
                    'text' => '⚠️ Файл ТЗ к предыдущей заявке доставить не удалось — попросите клиента прислать его ещё раз.',
                ]),
                self::TIMEOUT_SECONDS,
            );
        }
    }

    private function buildText(LeadMessageDto $lead): string
    {
        $lines = [
            '🆕 <b>Новая заявка с сайта</b>',
            '',
            '👤 <b>Имя:</b> ' . $this->escape($lead->name),
            '📞 <b>Телефон:</b> ' . $this->escape($lead->phone),
        ];

        if ('' !== $lead->email) {
            $lines[] = '✉️ <b>Email:</b> ' . $this->escape($lead->email);
        }

        $lines[] = '';
        $lines[] = '📝 <b>Описание:</b>';
        $lines[] = $this->escape($lead->description);

        if ('' !== $lead->page) {
            $lines[] = '';
            $lines[] = '🔗 ' . $this->escape($lead->page);
        }

        return implode("\n", $lines);
    }

    private function buildCaption(LeadMessageDto $lead): string
    {
        return '📎 <b>ТЗ к заявке от ' . $this->escape($lead->name) . '</b>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
