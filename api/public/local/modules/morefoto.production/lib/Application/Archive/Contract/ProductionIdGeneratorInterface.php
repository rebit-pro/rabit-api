<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Contract;

interface ProductionIdGeneratorInterface
{
    public function uuid(): string;
}
