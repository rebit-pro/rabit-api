<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\Contract;

interface DownloadIdGeneratorInterface
{
    public function uuid(): string;
}
