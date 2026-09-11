<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Domain\LeadHunt\Enum;

/**
 * Статус доставки внешней заявки в Telegram.
 */
enum LeadStatusEnum: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
}
