<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Controller;

use Morefoto\Support\Application\Question\UseCase\SendGuestFeedbackUseCase;
use Morefoto\Support\Presentation\Feedback\FeedbackMapper;
use Morefoto\Support\Presentation\Feedback\Request\Dto\SendFeedbackRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;

final class GuestFeedbackController extends PrivateApiJsonController
{
    public function __construct(
        private readonly SendGuestFeedbackUseCase $send,
        private readonly FeedbackMapper $mapper,
    ) {
        parent::__construct();
    }

    public function sendAction(SendFeedbackRequestDto $request): ControllerJson
    {
        return $this->acceptedJson($this->mapper->accepted($this->send->execute($this->mapper->input($request), $this->mapper->key($request))));
    }
}
