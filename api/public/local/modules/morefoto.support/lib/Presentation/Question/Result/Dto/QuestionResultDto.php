<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Question\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class QuestionResultDto implements ResultDtoInterface
{
    /** @param list<QuestionMessageResultDto> $messages */
    public function __construct(
        public ?int $id,
        public ?int $number,
        public array $messages,
    ) {}
}
