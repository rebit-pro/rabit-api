<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Access;

use Rebit\Share\Contracts\Access\Dto\LinkActorOutputDto;

/** Server-confirmed role and scope of staff for group link screens and commands. */
interface GroupLinkAccessInterface
{
    /** Re-reads Access for every call; takes no locks. */
    public function actor(int $userId): LinkActorOutputDto;

    /** First step of a link command inside the caller's transaction, before Organization locks the group. */
    public function lockState(): void;

    /** After lockState and the Organization group lock: locks the actor profile and Auth identity, then re-reads the scope. */
    public function lockActor(int $userId): LinkActorOutputDto;
}
