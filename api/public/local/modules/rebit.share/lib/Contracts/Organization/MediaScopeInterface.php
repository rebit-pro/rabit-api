<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization;

use Rebit\Share\Contracts\Organization\Dto\MediaGroupOutputDto;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;

interface MediaScopeInterface
{
    public function resolve(string $shootId, ?string $groupId = null): MediaScopeOutputDto;

    /** @return list<MediaGroupOutputDto> */
    public function groups(string $shootId): array;
}
