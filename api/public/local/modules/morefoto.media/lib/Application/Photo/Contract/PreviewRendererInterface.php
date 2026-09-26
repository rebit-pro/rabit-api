<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Contract;

use Morefoto\Media\Application\Photo\Dto\PreviewOutputDto;

interface PreviewRendererInterface
{
    public function render(string $originalPath, string $mimeType, string $photoId): PreviewOutputDto;

    public function remove(string $photoId): void;
}
