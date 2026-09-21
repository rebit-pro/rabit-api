<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Request\Dto;

use Morefoto\Handoff\Presentation\Request\StaffRequestValidation;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class StaffRequestDetailRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(
            name: 'staff_request_id',
            pattern: StaffRequestValidation::UUID_PATTERN,
        )]
        public string $requestId,
    ) {}
}
