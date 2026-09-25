<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Service;

use Morefoto\Support\Domain\Question\Repository\QuestionRepositoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Находит беседу родителя только по её личному ключу: отсутствующий, неверный и чужой ключ неразличимы (404). */
final readonly class ParentQuestionAccess
{
    public function __construct(private QuestionRepositoryInterface $questions) {}

    /** @return array{id: int, authorName: string} */
    public function find(?string $questionKey): array
    {
        $question = null === $questionKey || 1 !== preg_match('/^[a-f0-9]{64}$/D', $questionKey)
            ? null
            : $this->questions->findParent(hash('sha256', $questionKey));
        if (null === $question) {
            throw new HttpException('QUESTION_NOT_FOUND', 404);
        }

        return $question;
    }
}
