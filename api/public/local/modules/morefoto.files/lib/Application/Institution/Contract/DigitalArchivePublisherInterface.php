<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\Contract;

interface DigitalArchivePublisherInterface
{
    public function build(string $id, int $attempt): void;
}
