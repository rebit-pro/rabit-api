<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Dto;

final readonly class LinkMarkupCorrectionOutputDto
{
    /**
     * @param null|string $startedAt начало исправления разметки, ATOM в зоне группы; null — исправление завершено
     */
    public function __construct(
        public int $revision,
        public ?string $startedAt,
    ) {}
}
