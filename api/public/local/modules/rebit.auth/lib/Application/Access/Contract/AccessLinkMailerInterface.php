<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\Contract;

use Rebit\Auth\Application\Access\Dto\AccessLinkMailInputDto;

/** Queues the invitation and password reset letters; the token leaves the server only inside the letter. */
interface AccessLinkMailerInterface
{
    public function sendInvitation(AccessLinkMailInputDto $mail): void;

    public function sendPasswordReset(AccessLinkMailInputDto $mail): void;
}
