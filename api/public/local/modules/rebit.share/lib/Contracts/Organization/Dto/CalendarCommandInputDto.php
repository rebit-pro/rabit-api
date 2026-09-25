<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

/** Команда подтверждения или продления календаря группы; проверку выполняет Organization до обращения к БД. */
final readonly class CalendarCommandInputDto
{
    public function __construct(
        public string $groupId,
        public int $actorUserId,
        public int $expectedRevision,
        public string $key,
        public string $reason,
    ) {}
}
