<?php

declare(strict_types=1);

namespace Rebit\Auth\Presentation\Access;

use Rebit\Auth\Application\Access\Dto\AcceptInvitationInputDto;
use Rebit\Auth\Application\Access\Dto\ChangePasswordInputDto;
use Rebit\Auth\Application\Access\Dto\ConfirmPasswordResetInputDto;
use Rebit\Auth\Application\Access\Dto\PasswordResetInputDto;
use Rebit\Auth\Presentation\Access\Dto\AcceptInvitationRequestDto;
use Rebit\Auth\Presentation\Access\Dto\ChangePasswordRequestDto;
use Rebit\Auth\Presentation\Access\Dto\ConfirmPasswordResetRequestDto;
use Rebit\Auth\Presentation\Access\Dto\PasswordResetRequestDto;

/** Stateless mapping of access link requests to application input. */
final readonly class AccessInputMapper
{
    /** Token of a personal link: 256 random bits in base64url without padding. */
    public const string TOKEN_PATTERN = '/^[A-Za-z0-9_-]{43}$/D';

    public function accept(AcceptInvitationRequestDto $request): AcceptInvitationInputDto
    {
        return new AcceptInvitationInputDto(token: $request->token, password: $request->password);
    }

    public function reset(PasswordResetRequestDto $request): PasswordResetInputDto
    {
        return new PasswordResetInputDto(email: $request->email);
    }

    public function confirm(ConfirmPasswordResetRequestDto $request): ConfirmPasswordResetInputDto
    {
        return new ConfirmPasswordResetInputDto(token: $request->token, password: $request->password);
    }

    public function change(ChangePasswordRequestDto $request): ChangePasswordInputDto
    {
        return new ChangePasswordInputDto(currentPassword: $request->currentPassword, newPassword: $request->newPassword);
    }
}
