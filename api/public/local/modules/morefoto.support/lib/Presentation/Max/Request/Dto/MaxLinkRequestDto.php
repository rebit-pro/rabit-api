<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Max\Request\Dto;

final readonly class MaxLinkRequestDto
{
    public function __construct(
        public string $type,
        public MaxMessageBodyRequestDto $message,
    ) {}
}
