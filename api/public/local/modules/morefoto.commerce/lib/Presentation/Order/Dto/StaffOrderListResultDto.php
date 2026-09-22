<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class StaffOrderListResultDto implements ResultDtoInterface
{
    /** @param list<StaffOrderResultDto> $items */
    public function __construct(public array $items) {}
}
