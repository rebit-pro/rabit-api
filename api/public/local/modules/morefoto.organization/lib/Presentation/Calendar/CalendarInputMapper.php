<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Calendar;

use Morefoto\Organization\Presentation\Calendar\Request\Dto\ExtendGroupCalendarRequestDto;
use Morefoto\Organization\Presentation\Calendar\Request\Dto\GetGroupCalendarRequestDto;
use Rebit\Share\Contracts\Organization\Dto\CalendarCommandInputDto;
use Rebit\Share\Shared\Exception\HttpException;

/** Преобразует проверенный HTTP-запрос продления в команду календаря. */
final readonly class CalendarInputMapper
{
    public const string UUID_PATTERN = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D';

    public function bearer(ExtendGroupCalendarRequestDto|GetGroupCalendarRequestDto $request): string
    {
        return substr($request->authorization, 7);
    }

    public function command(ExtendGroupCalendarRequestDto $request, int $actorId): CalendarCommandInputDto
    {
        if (!$request->confirmed) {
            throw new HttpException('CONFIRMATION_REQUIRED', 422);
        }
        if (1 > $request->days || 365 < $request->days || 1 > $request->revision) {
            throw new HttpException('INVALID_EXTENSION_DAYS', 422);
        }
        if (1 !== preg_match('/^[a-f0-9]{32}$/D', $request->idempotencyKey)) {
            throw new HttpException('INVALID_IDEMPOTENCY_KEY', 422);
        }

        return new CalendarCommandInputDto(
            groupId: $request->groupId,
            actorUserId: $actorId,
            expectedRevision: $request->revision,
            key: $request->idempotencyKey,
            reason: 'Продление галереи на ' . $request->days . ' дн.',
        );
    }
}
