<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Dto;

use Morefoto\Organization\Application\Institution\Dto\InstitutionMutationInputDto;
use Rebit\Share\Application\Interface\RequestDtoInterface;

final readonly class InstitutionRequestDto implements RequestDtoInterface
{
    public function __construct(public InstitutionMutationInputDto $input) {}
}
