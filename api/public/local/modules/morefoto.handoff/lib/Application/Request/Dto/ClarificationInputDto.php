<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Dto;

final readonly class ClarificationInputDto
{
    public function __construct(public int $revision, public string $comment, public bool $confirmed) {}
}
