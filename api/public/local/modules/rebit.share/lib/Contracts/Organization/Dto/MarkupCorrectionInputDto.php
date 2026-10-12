<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class MarkupCorrectionInputDto
{
    public function __construct(
        public string $groupId,
        public int $actorUserId,
        public string $operationId,
    ) {}
}
