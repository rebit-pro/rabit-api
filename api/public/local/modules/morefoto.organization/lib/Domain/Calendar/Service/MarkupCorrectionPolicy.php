<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Calendar\Service;

use Morefoto\Organization\Domain\Calendar\Exception\CalendarRuleViolation;

/**
 * Правило D4: разметку исправляют только у переданной группы, пока она продаёт печать или электронные фото. До передачи
 * разметку просто правят, а после окончания продаж исправлять уже нечего.
 */
final readonly class MarkupCorrectionPolicy
{
    /** @throws CalendarRuleViolation LINK_NOT_SENT или GALLERY_CLOSED */
    public function assertCanStart(string $status): void
    {
        match ($status) {
            'open', 'digital' => null,
            'preparing' => throw new CalendarRuleViolation('LINK_NOT_SENT'),
            default => throw new CalendarRuleViolation('GALLERY_CLOSED'),
        };
    }
}
