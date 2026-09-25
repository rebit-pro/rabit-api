<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Notification\Enum;

enum MaxSendStatusEnum: string
{
    /** MAX принял сообщение и вернул его mid. */
    case DELIVERED = 'delivered';
    /** Окончательный отказ: повтор без изменений не поможет. */
    case REJECTED = 'rejected';
    /** Запрос не был принят (лимит, 5xx, соединение не установлено) — безопасно повторить. */
    case RETRY = 'retry';
    /** Запрос мог дойти до MAX: автоматический повтор может создать дубль. */
    case UNKNOWN = 'unknown';
}
