<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\UseCase;

use Morefoto\Support\Application\Question\Dto\QuestionOutputDto;
use Morefoto\Support\Application\Question\Service\ParentQuestionAccess;
use Morefoto\Support\Application\Question\Service\QuestionHistory;

/** Родитель по личному ключу беседы читает свою историю с ответами кураторов; чужой или неверный ключ — единый 404. */
final readonly class GetGalleryQuestionUseCase
{
    public function __construct(
        private ParentQuestionAccess $access,
        private QuestionHistory $history,
    ) {}

    public function execute(?string $questionKey): QuestionOutputDto
    {
        return $this->history->output($this->access->find($questionKey)['id']);
    }
}
