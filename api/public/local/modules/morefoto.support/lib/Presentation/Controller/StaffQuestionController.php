<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Controller;

use Morefoto\Support\Application\Question\UseCase\AddStaffQuestionMessageUseCase;
use Morefoto\Support\Application\Question\UseCase\GetStaffQuestionUseCase;
use Morefoto\Support\Presentation\Question\QuestionInputMapper;
use Morefoto\Support\Presentation\Question\QuestionResultMapper;
use Morefoto\Support\Presentation\Question\Request\Dto\AddStaffQuestionMessageRequestDto;
use Morefoto\Support\Presentation\Question\Request\Dto\StaffQuestionRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class StaffQuestionController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly GetStaffQuestionUseCase $mine,
        private readonly AddStaffQuestionMessageUseCase $add,
        private readonly QuestionInputMapper $inputMapper,
        private readonly QuestionResultMapper $resultMapper,
    ) {
        parent::__construct();
    }

    public function mineAction(StaffQuestionRequestDto $request): ControllerJson
    {
        return $this->json($this->resultMapper->question($this->mine->execute($this->getAuthUserId())));
    }

    public function messageAction(AddStaffQuestionMessageRequestDto $request): ControllerJson
    {
        return $this->json($this->resultMapper->question(
            $this->add->execute($this->getAuthUserId(), $request->message, $this->inputMapper->key($request)),
        ));
    }
}
