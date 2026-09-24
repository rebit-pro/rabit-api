<?php

declare(strict_types=1);

namespace Rebit\Auth\Presentation\Controller;

use Rebit\Auth\Application\Access\UseCase\AcceptAccessInvitationUseCase;
use Rebit\Auth\Application\Access\UseCase\ConfirmPasswordResetUseCase;
use Rebit\Auth\Application\Access\UseCase\GetAccessInvitationUseCase;
use Rebit\Auth\Application\Access\UseCase\RequestPasswordResetUseCase;
use Rebit\Auth\Presentation\Access\AccessInputMapper;
use Rebit\Auth\Presentation\Access\Dto\AcceptInvitationRequestDto;
use Rebit\Auth\Presentation\Access\Dto\AccessInvitationRequestDto;
use Rebit\Auth\Presentation\Access\Dto\ConfirmPasswordResetRequestDto;
use Rebit\Auth\Presentation\Access\Dto\PasswordResetRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\EmptyResponse;

/** Personal links from letters: invitation (AUTH-05, AUTH-06) and forgotten password (AUTH-07, AUTH-08). */
final class AccessLinkController extends PrivateApiJsonController
{
    public function __construct(
        private readonly GetAccessInvitationUseCase $invitation,
        private readonly AcceptAccessInvitationUseCase $acceptInvitation,
        private readonly RequestPasswordResetUseCase $requestReset,
        private readonly ConfirmPasswordResetUseCase $confirmReset,
        private readonly AccessInputMapper $input,
    ) {
        parent::__construct();
    }

    public function invitationAction(AccessInvitationRequestDto $request): ControllerJson
    {
        return $this->json($this->invitation->execute($request->token));
    }

    public function acceptInvitationAction(AcceptInvitationRequestDto $request): ControllerJson
    {
        return $this->json($this->acceptInvitation->execute($this->input->accept($request)));
    }

    public function requestPasswordResetAction(PasswordResetRequestDto $request): EmptyResponse
    {
        $this->requestReset->execute($this->input->reset($request));

        return $this->accepted();
    }

    public function confirmPasswordResetAction(ConfirmPasswordResetRequestDto $request): ControllerJson
    {
        return $this->json($this->confirmReset->execute($this->input->confirm($request)));
    }
}
