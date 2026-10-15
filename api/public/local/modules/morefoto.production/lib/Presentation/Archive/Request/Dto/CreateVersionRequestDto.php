<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Archive\Request\Dto;

use Morefoto\Production\Presentation\Archive\ProductionInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class CreateVersionRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'group_id', pattern: ProductionInputMapper::UUID_PATTERN)]
        public string $groupId,
        #[RequestHeader('Idempotency-Key')]
        public string $idempotencyKey,
    ) {}
}
