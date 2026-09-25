<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Avatar\Contract;

use Morefoto\Access\Application\Avatar\Dto\InspectedAvatarDto;
use Morefoto\Access\Application\Avatar\Dto\RenderedAvatarDto;
use Rebit\Share\Shared\Exception\HttpException;

interface AvatarRendererInterface
{
    /**
     * @throws HttpException 422 CORRUPTED_AVATAR when the image cannot be decoded
     */
    public function render(InspectedAvatarDto $avatar): RenderedAvatarDto;
}
