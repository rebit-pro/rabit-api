<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Dto;

final readonly class StaffQuestionContextDto
{
    /** @param list<string> $institutionNames */
    public function __construct(
        public int $userId,
        public string $name,
        public string $role,
        public array $institutionNames,
    ) {}
}
