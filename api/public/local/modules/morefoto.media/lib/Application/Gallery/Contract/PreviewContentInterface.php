<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\Contract;

use Rebit\Share\Application\Contract\File\Dto\PreviewContentOutputDto;

interface PreviewContentInterface
{
    public function read(string $photoId, string $variant): PreviewContentOutputDto;
}
