<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Controller;

use Morefoto\Support\Application\Question\UseCase\AddGalleryQuestionMessageUseCase;
use Morefoto\Support\Application\Question\UseCase\AskGalleryQuestionUseCase;
use Morefoto\Support\Application\Question\UseCase\GetGalleryQuestionUseCase;
use Morefoto\Support\Presentation\Question\QuestionInputMapper;
use Morefoto\Support\Presentation\Question\QuestionResultMapper;
use Morefoto\Support\Presentation\Question\Request\Dto\AddQuestionMessageRequestDto;
use Morefoto\Support\Presentation\Question\Request\Dto\AskQuestionRequestDto;
use Morefoto\Support\Presentation\Question\Request\Dto\CurrentQuestionRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;

final class GalleryQuestionController extends PrivateApiJsonController
{
    public function __construct(
        private readonly AskGalleryQuestionUseCase $ask,
        private readonly GetGalleryQuestionUseCase $current,
        private readonly AddGalleryQuestionMessageUseCase $add,
        private readonly QuestionInputMapper $inputMapper,
        private readonly QuestionResultMapper $resultMapper,
    ) {
        parent::__construct();
    }

    public function askAction(AskQuestionRequestDto $request): ControllerJson
    {
        $output = $this->ask->execute($this->inputMapper->ask($request), $this->inputMapper->key($request));

        return $this->createdJson($this->resultMapper->created($output), $this->resultMapper->location());
    }

    public function currentAction(CurrentQuestionRequestDto $request): ControllerJson
    {
        return $this->json($this->resultMapper->question($this->current->execute($request->questionKey)));
    }

    public function messageAction(AddQuestionMessageRequestDto $request): ControllerJson
    {
        return $this->json($this->resultMapper->question(
            $this->add->execute($request->questionKey, $request->message, $this->inputMapper->key($request)),
        ));
    }
}
