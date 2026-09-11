<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Lead\Port;

use Rebit\Notification\Application\Lead\Dto\LeadAttachmentDto;
use Rebit\Notification\Application\Lead\Dto\LeadMessageDto;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Порт доставки заявки ответственному получателю (Telegram, email, ...).
 */
interface LeadNotifierInterface
{
    /**
     * @param null|LeadAttachmentDto $attachment опциональный файл ТЗ
     *
     * @throws HttpException если доставку не удалось выполнить
     */
    public function notify(LeadMessageDto $lead, ?LeadAttachmentDto $attachment = null): void;
}
