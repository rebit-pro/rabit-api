<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\UseCase;

use Morefoto\Support\Application\Question\Contract\StaffQuestionContextInterface;
use Morefoto\Support\Application\Question\Dto\QuestionOutputDto;
use Morefoto\Support\Application\Question\Service\QuestionHistory;
use Morefoto\Support\Domain\Question\Repository\QuestionRepositoryInterface;

/** Заведующая или воспитатель читает свою единственную беседу с кураторами; до первого вопроса история пуста. */
final readonly class GetStaffQuestionUseCase
{
    public function __construct(
        private StaffQuestionContextInterface $staff,
        private QuestionRepositoryInterface $questions,
        private QuestionHistory $history,
    ) {}

    public function execute(int $userId): QuestionOutputDto
    {
        $this->staff->resolve($userId);

        return $this->history->output($this->questions->findStaff($userId)['id'] ?? null);
    }
}
