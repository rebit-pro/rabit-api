<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Calendar\Service;

use Rebit\Share\Contracts\Organization\Dto\CalendarCommandInputDto;

/**
 * Отклоняет команду календаря группы до любого обращения к БД: канонический UUID группы, ключ журнала из 32 hex-символов,
 * положительный actor, ревизия с запасом на инкремент и непустая UTF-8 причина до 1000 символов.
 */
final readonly class CalendarCommandValidator
{
    /** @throws \InvalidArgumentException */
    public function validate(CalendarCommandInputDto $input): void
    {
        if (1 !== preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $input->groupId)) {
            throw new \InvalidArgumentException('A canonical lowercase group UUID is required.');
        }
        if (1 !== preg_match('/^[a-f0-9]{32}$/D', $input->key)) {
            throw new \InvalidArgumentException('The calendar idempotency key must be 32 lowercase hexadecimal characters.');
        }
        if (1 > $input->actorUserId || 1 > $input->expectedRevision || 2147483646 < $input->expectedRevision) {
            throw new \InvalidArgumentException('A positive actor ID and incrementable group revision are required.');
        }
        $reason = $input->reason;
        if ('' === trim($reason) || !mb_check_encoding($reason, 'UTF-8') || str_contains($reason, "\0") || 1000 < mb_strlen($reason)) {
            throw new \InvalidArgumentException('A nonempty UTF-8 reason of at most 1000 characters is required.');
        }
    }
}
