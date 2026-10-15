<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Archive\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class ProductionGroupsRequestDto implements RequestDtoInterface
{
    /** @param string $groupIds публичные ID групп через запятую, не больше 100 */
    public function __construct(
        public string $groupIds = '',
    ) {}
}
