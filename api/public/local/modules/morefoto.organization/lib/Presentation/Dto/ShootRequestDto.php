<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Dto;

use Morefoto\Organization\Application\Structure\Dto\ShootMutationInputDto;
use Rebit\Share\Application\Interface\RequestDtoInterface;

final readonly class ShootRequestDto implements RequestDtoInterface
{
    public function __construct(public ShootMutationInputDto $input) {}
}
