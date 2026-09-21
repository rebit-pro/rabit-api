<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Request\Dto;

use Morefoto\Handoff\Presentation\Request\StaffRequestValidation;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody]
#[StrictRequest]
final readonly class UpdateStaffRequestRequestDto implements RequestDtoInterface
{
    /** @param list<StaffRequestRowRequestDto> $rows */
    public function __construct(
        public string $institutionId,
        public string $shootId,
        /** @var StaffRequestRowRequestDto[] */
        public array $rows,
        public string $comment,
        public int $revision,
        #[RouteParameter(
            name: 'staff_request_id',
            pattern: StaffRequestValidation::UUID_PATTERN,
        )]
        public string $requestId,
        #[RequestHeader('Idempotency-Key')]
        public string $idempotencyKey,
    ) {}
}
