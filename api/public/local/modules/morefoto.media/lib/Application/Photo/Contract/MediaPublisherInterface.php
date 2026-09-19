<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Contract;

interface MediaPublisherInterface
{
    public function process(string $photoId): void;
}
