<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Shoot\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class ShootListRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RequestHeader(name: 'Authorization')]
        public string $authorization,
        public ?string $q = null,
        public int $page = 1,
        public int $pageSize = 25,
        public ?string $sort = null,
        public ?string $direction = null,
    ) {}
}
