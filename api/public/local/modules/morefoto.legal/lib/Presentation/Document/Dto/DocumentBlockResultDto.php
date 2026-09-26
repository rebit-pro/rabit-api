<?php

declare(strict_types=1);

namespace Morefoto\Legal\Presentation\Document\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class DocumentBlockResultDto implements ResultDtoInterface
{
    /** @param list<string> $items */
    public function __construct(
        public string $type,
        public string $text,
        public int $level,
        public array $items,
    ) {}
}
