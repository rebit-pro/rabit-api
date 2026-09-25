<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Question;

use Morefoto\Support\Application\Question\Dto\AskGalleryQuestionInputDto;
use Morefoto\Support\Domain\Question\ValueObject\IdempotencyKey;
use Morefoto\Support\Presentation\Question\Request\Dto\AddQuestionMessageRequestDto;
use Morefoto\Support\Presentation\Question\Request\Dto\AddStaffQuestionMessageRequestDto;
use Morefoto\Support\Presentation\Question\Request\Dto\AskQuestionRequestDto;

/** Converts presentation request DTOs of questions into Application input and value objects. */
final readonly class QuestionInputMapper
{
    public function ask(AskQuestionRequestDto $request): AskGalleryQuestionInputDto
    {
        return new AskGalleryQuestionInputDto($request->token, $request->name, $request->message);
    }

    public function key(AddQuestionMessageRequestDto|AddStaffQuestionMessageRequestDto|AskQuestionRequestDto $request): IdempotencyKey
    {
        return new IdempotencyKey($request->idempotencyKey);
    }
}
