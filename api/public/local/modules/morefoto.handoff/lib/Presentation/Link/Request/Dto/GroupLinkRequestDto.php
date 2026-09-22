<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Link\Request\Dto;

use Morefoto\Handoff\Presentation\Request\StaffRequestValidation;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class GroupLinkRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(
            name: 'group_id',
            pattern: StaffRequestValidation::UUID_PATTERN,
        )]
        public string $groupId,
    ) {}
}
