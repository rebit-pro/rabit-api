<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Staff\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class StaffListRequestDto implements RequestDtoInterface
{
    public function __construct(
        public ?string $q = null,
        public ?string $role = null,
        public ?string $active = null,
        public ?string $accountStatus = null,
        public ?string $page = null,
        public ?string $pageSize = null,
        public ?string $sort = null,
        public ?string $direction = null,
    ) {}
}
