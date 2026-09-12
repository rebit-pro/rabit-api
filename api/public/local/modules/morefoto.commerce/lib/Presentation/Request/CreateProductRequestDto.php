<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Request;

use Morefoto\Commerce\Application\Catalog\Dto\ProductInputDto;
use Morefoto\Commerce\Domain\Catalog\ValueObject\IdempotencyKey;
use Rebit\Share\Application\Interface\RequestDtoInterface;

final readonly class CreateProductRequestDto implements RequestDtoInterface
{
    public function __construct(public ProductInputDto $input, public IdempotencyKey $idempotencyKey) {}
}
