<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class LinkSentInputDto
{
    public function __construct(
        public string $groupId,
        public int $actorUserId,
        public string $operationId,
        public \DateTimeImmutable $sentAt,
        public ?string $reason,
    ) {}
}
