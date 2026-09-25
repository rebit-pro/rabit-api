<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Dto;

final readonly class QuestionOutputDto
{
    /** @param list<QuestionMessageOutputDto> $messages */
    public function __construct(
        public ?int $id,
        public array $messages,
        public ?string $questionKey = null,
    ) {}
}
