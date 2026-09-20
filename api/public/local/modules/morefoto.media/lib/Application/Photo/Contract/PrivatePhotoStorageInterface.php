<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Contract;

use Morefoto\Media\Application\Photo\Dto\InspectedPhoto;

interface PrivatePhotoStorageInterface
{
    public function store(string $shootId, InspectedPhoto $photo): string;

    public function absolutePath(string $relativePath): string;

    public function delete(string $relativePath): void;
}
