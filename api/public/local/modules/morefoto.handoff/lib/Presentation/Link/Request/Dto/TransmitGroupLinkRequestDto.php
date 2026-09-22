<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Link\Request\Dto;

use Morefoto\Handoff\Presentation\Request\StaffRequestValidation;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody]
#[StrictRequest]
final readonly class TransmitGroupLinkRequestDto implements RequestDtoInterface
{
    public function __construct(
        public int $revision,
        public string $signature,
        public string $sentAt,
        public bool $confirmed,
        #[RouteParameter(
            name: 'group_id',
            pattern: StaffRequestValidation::UUID_PATTERN,
        )]
        public string $groupId,
        #[RequestHeader('Idempotency-Key')]
        public string $idempotencyKey,
    ) {}
}
