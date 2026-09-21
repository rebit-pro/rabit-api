<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody]
#[StrictRequest]
final readonly class CreateStaffRequestRequestDto implements RequestDtoInterface
{
    /** @param list<StaffRequestRowRequestDto> $rows */
    public function __construct(
        public string $institutionId,
        public string $shootId,
        /** @var StaffRequestRowRequestDto[] */
        public array $rows,
        public string $comment,
        #[RequestHeader('Idempotency-Key')]
        public string $idempotencyKey,
    ) {}
}
