<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Authorization\Dto;

use Morefoto\Access\Domain\Staff\Entity\StaffProfile;
use Rebit\Share\Application\Contract\Auth\Dto\IdentityOutputDto;

final readonly class StaffContextOutputDto
{
    public function __construct(public IdentityOutputDto $identity, public StaffProfile $profile) {}
}
