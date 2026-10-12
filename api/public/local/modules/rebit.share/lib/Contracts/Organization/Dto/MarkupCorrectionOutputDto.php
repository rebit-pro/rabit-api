<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class MarkupCorrectionOutputDto
{
    /**
     * @param null|string $startedAt начало исправления разметки, ATOM в зоне группы; null — продажи идут
     */
    public function __construct(
        public string $groupId,
        public int $revision,
        public ?string $startedAt,
    ) {}
}
