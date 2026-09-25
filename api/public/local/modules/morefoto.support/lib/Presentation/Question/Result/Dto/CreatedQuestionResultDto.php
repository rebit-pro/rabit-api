<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Question\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class CreatedQuestionResultDto implements ResultDtoInterface
{
    /** @param list<QuestionMessageResultDto> $messages */
    public function __construct(
        public int $id,
        public int $number,
        public string $questionKey,
        public array $messages,
    ) {}
}
