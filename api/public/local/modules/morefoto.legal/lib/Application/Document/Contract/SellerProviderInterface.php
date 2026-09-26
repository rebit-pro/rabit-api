<?php

declare(strict_types=1);

namespace Morefoto\Legal\Application\Document\Contract;

use Morefoto\Legal\Application\Document\Dto\SellerOutputDto;

interface SellerProviderInterface
{
    public function seller(): SellerOutputDto;
}
