<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Notification\Enum;

enum MaxSendStatusEnum: string
{
    /** MAX принял сообщение и вернул его mid. */
    case DELIVERED = 'delivered';
    /** Окончательный отказ: повтор без изменений не поможет. */
    case REJECTED = 'rejected';
    /** Запрос заведомо не обработан (соединение не установлено, лимит 429) — безопасно повторить. */
    case RETRY = 'retry';
    /** Запрос мог дойти до MAX (таймаут, 5xx, ответ шлюза): автоматический повтор может создать дубль. */
    case UNKNOWN = 'unknown';
}
