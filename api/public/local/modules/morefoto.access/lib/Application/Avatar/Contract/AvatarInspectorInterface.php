<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Avatar\Contract;

use Morefoto\Access\Application\Avatar\Dto\InspectedAvatarDto;
use Morefoto\Access\Application\Avatar\Dto\UploadedAvatarInputDto;
use Rebit\Share\Shared\Exception\HttpException;

interface AvatarInspectorInterface
{
    /**
     * @throws HttpException 422 AVATAR_TOO_LARGE, AVATAR_TOO_SMALL, UNSUPPORTED_AVATAR_FORMAT or CORRUPTED_AVATAR
     */
    public function inspect(UploadedAvatarInputDto $upload): InspectedAvatarDto;
}
