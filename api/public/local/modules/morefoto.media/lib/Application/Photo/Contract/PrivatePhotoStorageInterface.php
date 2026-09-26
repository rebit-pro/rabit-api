<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Contract;

use Morefoto\Media\Application\Photo\Dto\InspectedPhoto;

interface PrivatePhotoStorageInterface
{
    /** The path is derived from the content, so repeated uploads of one file share it. */
    public function path(string $shootId, InspectedPhoto $photo): string;

    public function store(string $shootId, InspectedPhoto $photo): string;

    public function absolutePath(string $relativePath): string;

    public function delete(string $relativePath): void;
}
