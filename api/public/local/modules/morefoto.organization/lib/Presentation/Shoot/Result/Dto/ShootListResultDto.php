<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Shoot\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class ShootListResultDto implements ResultDtoInterface
{
    /** @param list<ShootListItemResultDto> $items */
    public function __construct(public array $items) {}
}
