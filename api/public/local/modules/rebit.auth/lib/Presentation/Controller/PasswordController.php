<?php

declare(strict_types=1);

namespace Rebit\Auth\Presentation\Controller;

use Rebit\Auth\Application\Access\UseCase\ChangePasswordUseCase;
use Rebit\Auth\Presentation\Access\AccessInputMapper;
use Rebit\Auth\Presentation\Access\Dto\ChangePasswordRequestDto;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\EmptyResponse;

/** Password change of the signed-in staff member (AUTH-09). */
final class PasswordController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly ChangePasswordUseCase $changePassword,
        private readonly AccessInputMapper $input,
    ) {
        parent::__construct();
    }

    public function changeAction(ChangePasswordRequestDto $request): EmptyResponse
    {
        $this->changePassword->execute($this->getAuthUserId(), $this->input->change($request));

        return $this->noContent();
    }
}
