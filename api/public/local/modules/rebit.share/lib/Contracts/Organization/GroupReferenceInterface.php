<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization;

use Rebit\Share\Contracts\Organization\Dto\GroupReferenceOutputDto;

interface GroupReferenceInterface
{
    /** Resolves a public group ID without exposing Organization persistence or Result. */
    public function get(string $groupId): GroupReferenceOutputDto;
}
