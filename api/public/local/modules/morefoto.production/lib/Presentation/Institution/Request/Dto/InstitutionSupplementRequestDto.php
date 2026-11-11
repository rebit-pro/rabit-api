<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Institution\Request\Dto;

use Morefoto\Production\Presentation\Archive\ProductionInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class InstitutionSupplementRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'institution_id', pattern: ProductionInputMapper::UUID_PATTERN)]
        public string $institutionId,
        #[RequestHeader('Idempotency-Key')]
        public string $idempotencyKey,
    ) {}
}
