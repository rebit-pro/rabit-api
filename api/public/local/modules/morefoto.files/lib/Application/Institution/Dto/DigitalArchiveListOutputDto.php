<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\Dto;

final readonly class DigitalArchiveListOutputDto
{
    /** @param list<DigitalArchiveOutputDto> $items */
    public function __construct(public bool $canCreate, public array $items) {}
}
