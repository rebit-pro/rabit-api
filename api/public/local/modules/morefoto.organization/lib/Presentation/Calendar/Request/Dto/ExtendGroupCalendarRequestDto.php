<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Calendar\Request\Dto;

use Morefoto\Organization\Presentation\Calendar\CalendarInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody]
#[StrictRequest]
final readonly class ExtendGroupCalendarRequestDto implements RequestDtoInterface
{
    public function __construct(
        public int $revision,
        public int $days,
        public bool $confirmed,
        #[RouteParameter(name: 'group_id', pattern: CalendarInputMapper::UUID_PATTERN, errorCode: 'NOT_FOUND', errorStatus: 404)]
        public string $groupId,
        #[RequestHeader(name: 'Authorization')]
        public string $authorization,
        #[RequestHeader(name: 'Idempotency-Key')]
        public string $idempotencyKey,
    ) {}
}
