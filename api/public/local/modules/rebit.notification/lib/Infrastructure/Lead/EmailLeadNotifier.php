<?php

declare(strict_types=1);

namespace Rebit\Notification\Infrastructure\Lead;

use Psr\Log\LoggerInterface;
use Rebit\Notification\Application\Lead\Dto\LeadAttachmentDto;
use Rebit\Notification\Application\Lead\Dto\LeadMessageDto;
use Rebit\Notification\Application\Lead\Port\LeadNotifierInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Доставка заявки с сайта письмом через почтовое событие Bitrix.
 *
 * Резервный канал на случай недоступности Telegram (блокировка, смерть прокси).
 * Файл ТЗ уходит вложением напрямую из PHP-temp загрузки: в b_file его не
 * сохраняем, чтобы не копить мусор в /upload от публичного эндпоинта.
 */
final readonly class EmailLeadNotifier implements LeadNotifierInterface
{
    public const string EVENT_NAME = 'REBIT_NOTIFICATION_LEAD';

    public function __construct(
        private LoggerInterface $logger,
        private string $email,
        private string $siteId,
    ) {}

    /**
     * @throws HttpException
     */
    public function notify(LeadMessageDto $lead, ?LeadAttachmentDto $attachment = null): void
    {
        if ('' === $this->email) {
            $this->logger->error('Email-получатель заявок не настроен: пустой REBIT_NOTIFICATION_LEAD_FALLBACK_EMAIL');

            throw new HttpException('Сервис заявок временно недоступен', 503);
        }

        $result = \CEvent::SendImmediate(
            self::EVENT_NAME,
            $this->siteId,
            $this->buildFields($lead, $attachment),
            'Y',
            '',
            [],
            '',
            $this->buildFilesContent($attachment),
        );

        if ('Y' !== $result) {
            $this->logger->error('Не удалось отправить заявку письмом', ['result' => (string)$result]);

            throw new HttpException('Не удалось отправить заявку', 502);
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildFields(LeadMessageDto $lead, ?LeadAttachmentDto $attachment): array
    {
        return [
            'EMAIL_TO' => $this->email,
            'NAME' => $this->escape($lead->name),
            'PHONE' => $this->escape($lead->phone),
            'EMAIL' => $this->escape($lead->email),
            'DESCRIPTION' => nl2br($this->escape($lead->description)),
            'PAGE' => $this->escape($lead->page),
            'FILE_NAME' => null === $attachment ? '' : $this->escape($attachment->name),
        ];
    }

    /**
     * Контент вложения для CEvent: файл читаем сами, без записи в b_file.
     *
     * @return list<array{
     *     CONTENT_TYPE: string,
     *     NAME: string,
     *     CONTENT: string,
     *     ID: string,
     *     CHARSET: string,
     *     METHOD: string,
     * }>
     */
    private function buildFilesContent(?LeadAttachmentDto $attachment): array
    {
        if (null === $attachment) {
            return [];
        }

        $content = @file_get_contents($attachment->path);

        if (false === $content) {
            // Заявка важнее вложения: письмо уходит без файла, получатель видит это по FILE_NAME.
            $this->logger->error('Не удалось прочитать файл ТЗ для письма', ['name' => $attachment->name]);

            return [];
        }

        return [[
            'CONTENT_TYPE' => $attachment->mimeType,
            'NAME' => $attachment->name,
            'CONTENT' => $content,
            'ID' => 'lead-attachment',
            'CHARSET' => '',
            'METHOD' => '',
        ]];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
