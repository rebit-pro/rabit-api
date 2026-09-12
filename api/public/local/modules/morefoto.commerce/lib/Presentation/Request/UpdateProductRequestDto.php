<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Request;

use Morefoto\Commerce\Application\Catalog\Dto\UpdateProductInputDto;
use Morefoto\Commerce\Domain\Catalog\ValueObject\IdempotencyKey;
use Rebit\Share\Application\Interface\RequestDtoInterface;

final readonly class UpdateProductRequestDto implements RequestDtoInterface
{
    public function __construct(public UpdateProductInputDto $input, public IdempotencyKey $idempotencyKey) {}
}
