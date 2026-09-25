<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Staff\Dto;

use Morefoto\Access\Presentation\Staff\StaffArchiveInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class ArchiveStaffRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'user_id', pattern: StaffArchiveInputMapper::USER_ID_PATTERN, errorCode: 'STAFF_NOT_FOUND', errorStatus: 404)]
        public string $userId,
    ) {}
}
