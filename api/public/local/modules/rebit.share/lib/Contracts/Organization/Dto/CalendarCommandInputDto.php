<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class CalendarCommandInputDto
{
    public function __construct(
        public string $groupId,
        public int $actorUserId,
        public int $expectedRevision,
        public string $key,
        public string $reason,
    ) {
        if (1 !== preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $groupId)) {
            throw new \InvalidArgumentException('A canonical lowercase group UUID is required.');
        }
        if (1 !== preg_match('/^[a-f0-9]{32}$/D', $key)) {
            throw new \InvalidArgumentException('The calendar idempotency key must be 32 lowercase hexadecimal characters.');
        }
        if (1 > $actorUserId || 1 > $expectedRevision || 2147483646 < $expectedRevision) {
            throw new \InvalidArgumentException('A positive actor ID and incrementable group revision are required.');
        }
        if ('' === trim($reason) || !mb_check_encoding($reason, 'UTF-8') || str_contains($reason, "\0") || 1000 < mb_strlen($reason)) {
            throw new \InvalidArgumentException('A nonempty UTF-8 reason of at most 1000 characters is required.');
        }
    }
}
