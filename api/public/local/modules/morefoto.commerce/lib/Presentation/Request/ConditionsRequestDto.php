<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Request;

use Morefoto\Commerce\Application\Conditions\Dto\SaveConditionsInputDto;
use Morefoto\Commerce\Domain\Catalog\ValueObject\IdempotencyKey;

final readonly class ConditionsRequestDto
{
    public function __construct(
        public SaveConditionsInputDto $input,
        public IdempotencyKey $idempotencyKey,
    ) {}
}
