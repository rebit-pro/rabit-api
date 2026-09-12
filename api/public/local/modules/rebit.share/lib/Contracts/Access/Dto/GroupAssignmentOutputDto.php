<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Access\Dto;

final readonly class GroupAssignmentOutputDto
{
    public function __construct(public ?int $teacherId = null) {}
}
