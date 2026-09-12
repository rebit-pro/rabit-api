<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Request;

use Morefoto\Commerce\Application\Catalog\Dto\ListProductsInputDto;
use Rebit\Share\Application\Interface\RequestDtoInterface;

final readonly class ListProductsRequestDto implements RequestDtoInterface
{
    public function __construct(public ListProductsInputDto $input) {}
}
