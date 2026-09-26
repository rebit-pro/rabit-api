<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\Contract;

interface FilesPublisherInterface
{
    public function build(string $downloadId, int $attempt): void;
}
