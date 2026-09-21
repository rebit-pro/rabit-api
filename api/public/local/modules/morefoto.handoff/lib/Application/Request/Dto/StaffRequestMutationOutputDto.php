<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Dto;

final readonly class StaffRequestMutationOutputDto
{
    public function __construct(public string $id, public int $revision, public string $status) {}
}
