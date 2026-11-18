<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Structure\Dto;

use Morefoto\Organization\Presentation\Calendar\CalendarInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody(maxBytes: 262144)]
#[StrictRequest]
final readonly class ImportGroupsRequestDto implements RequestDtoInterface
{
    /** @param list<string> $groups */
    public function __construct(
        /** @var string[] */
        public array $groups,
        #[RouteParameter(name: 'institution_id', pattern: CalendarInputMapper::UUID_PATTERN)]
        public string $institutionId,
        #[RouteParameter(name: 'shoot_id', pattern: CalendarInputMapper::UUID_PATTERN)]
        public string $shootId,
        #[RequestHeader('Idempotency-Key')]
        public string $key,
        #[RequestHeader('Authorization')]
        public string $authorization,
    ) {}
}
