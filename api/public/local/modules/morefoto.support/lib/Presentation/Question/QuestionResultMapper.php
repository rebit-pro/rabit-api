<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Question;

use Morefoto\Support\Application\Question\Dto\QuestionMessageOutputDto;
use Morefoto\Support\Application\Question\Dto\QuestionOutputDto;
use Morefoto\Support\Presentation\Question\Result\Dto\CreatedQuestionResultDto;
use Morefoto\Support\Presentation\Question\Result\Dto\QuestionMessageResultDto;
use Morefoto\Support\Presentation\Question\Result\Dto\QuestionResultDto;

/** Maps question history into API results; internal delivery states become sending/delivered/failed/unknown. */
final readonly class QuestionResultMapper
{
    public function question(QuestionOutputDto $output): QuestionResultDto
    {
        return new QuestionResultDto($output->id, $output->id, $this->messages($output));
    }

    public function created(QuestionOutputDto $output): CreatedQuestionResultDto
    {
        return new CreatedQuestionResultDto((int)$output->id, (int)$output->id, (string)$output->questionKey, $this->messages($output));
    }

    public function location(): string
    {
        return '/api/v1/public/questions/current';
    }

    /** @return list<QuestionMessageResultDto> */
    private function messages(QuestionOutputDto $output): array
    {
        return array_map(
            static fn(QuestionMessageOutputDto $message): QuestionMessageResultDto => new QuestionMessageResultDto(
                $message->id,
                $message->author,
                $message->authorName,
                $message->text,
                $message->createdAt,
                match ($message->deliveryStatus) {
                    null => null,
                    'pending', 'processing' => 'sending',
                    // Terminal: MAX may or may not have the reply, and it is never sent again automatically.
                    'unknown' => 'unknown',
                    'delivered' => 'delivered',
                    default => 'failed',
                },
            ),
            $output->messages,
        );
    }
}
