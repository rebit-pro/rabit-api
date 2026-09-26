<?php

declare(strict_types=1);

namespace Morefoto\Legal\Application\Document\Dto;

/** Блок текста: heading (level 2–3), paragraph (text) или list (items). */
final readonly class DocumentBlockOutputDto
{
    /** @param list<string> $items */
    public function __construct(
        public string $type,
        public string $text = '',
        public int $level = 0,
        public array $items = [],
    ) {}
}
