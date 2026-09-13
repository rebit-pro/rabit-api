<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Dto;

use Morefoto\Organization\Application\Structure\Dto\GroupMutationInputDto;
use Rebit\Share\Application\Interface\RequestDtoInterface;

final readonly class GroupRequestDto implements RequestDtoInterface
{
    public function __construct(public GroupMutationInputDto $input) {}
}
