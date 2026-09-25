<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Conditions\Dto;

use Morefoto\Commerce\Presentation\Conditions\ConditionsInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class GroupConditionsRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'group_id', pattern: ConditionsInputMapper::GROUP_ID_PATTERN, errorCode: 'NOT_FOUND', errorStatus: 404)]
        public string $groupId,
        #[RequestHeader('Authorization')]
        public string $authorization,
    ) {}
}
