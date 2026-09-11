<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Domain\LeadHunt\Enum;

/**
 * Внешняя площадка — источник заявок.
 *
 * Новая площадка = новый case + реализация LeadFeedInterface + запись в реестре фидов (di/LeadHunt.php).
 */
enum LeadSourceEnum: string
{
    case FL_RU = 'flRu';

    public function title(): string
    {
        return match ($this) {
            self::FL_RU => 'FL.ru',
        };
    }
}
