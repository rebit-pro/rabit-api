<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Profile\Contract;

use Morefoto\Access\Application\Profile\Dto\SupportContactOutputDto;

/** The organizer's contact for staff who need help with access (DS-12); null when none is configured. */
interface SupportContactProviderInterface
{
    public function contact(): ?SupportContactOutputDto;
}
