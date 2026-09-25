<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Max\Request\Dto;

final readonly class MaxMessageBodyRequestDto
{
    public function __construct(
        public string $mid,
        public ?string $text = null,
    ) {}
}
