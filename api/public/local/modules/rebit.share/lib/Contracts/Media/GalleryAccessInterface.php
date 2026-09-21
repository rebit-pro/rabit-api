<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media;

use Rebit\Share\Contracts\Media\Dto\GalleryAccessOutputDto;
use Rebit\Share\Contracts\Media\Dto\GalleryContextOutputDto;

interface GalleryAccessInterface
{
    public function context(string $token): GalleryContextOutputDto;

    public function resolve(string $token): GalleryAccessOutputDto;
}
