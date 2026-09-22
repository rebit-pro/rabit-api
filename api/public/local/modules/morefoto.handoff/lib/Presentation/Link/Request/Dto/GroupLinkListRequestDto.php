<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Link\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class GroupLinkListRequestDto implements RequestDtoInterface
{
    public function __construct(
        public ?string $institutionId = null,
        public ?string $shootId = null,
        public ?string $state = null,
        public int $page = 1,
        public int $pageSize = 25,
    ) {}
}
