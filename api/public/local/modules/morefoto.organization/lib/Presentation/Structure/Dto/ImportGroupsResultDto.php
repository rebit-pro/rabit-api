<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Structure\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class ImportGroupsResultDto implements ResponseDtoInterface
{
    /** @param list<string> $groupIds */
    public function __construct(public array $groupIds) {}
}
