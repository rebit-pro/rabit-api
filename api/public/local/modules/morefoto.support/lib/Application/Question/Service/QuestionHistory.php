<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Service;

use Morefoto\Support\Application\Question\Dto\QuestionMessageOutputDto;
use Morefoto\Support\Application\Question\Dto\QuestionOutputDto;
use Morefoto\Support\Domain\Question\Repository\QuestionRepositoryInterface;

/** Отдаёт историю беседы в порядке создания: автору видны его реплики со статусом доставки и ответы куратора. */
final readonly class QuestionHistory
{
    public function __construct(private QuestionRepositoryInterface $questions) {}

    public function output(?int $questionId, ?string $questionKey = null): QuestionOutputDto
    {
        if (null === $questionId) {
            return new QuestionOutputDto(null, []);
        }
        $messages = [];
        foreach ($this->questions->messages($questionId) as $row) {
            $messages[] = new QuestionMessageOutputDto(
                $row['id'],
                $row['author'],
                $row['authorName'],
                $row['body'],
                $row['createdAt'],
                $row['deliveryStatus'],
            );
        }

        return new QuestionOutputDto($questionId, $messages, $questionKey);
    }
}
