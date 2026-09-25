<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Conditions\Dto;

use Morefoto\Commerce\Presentation\Conditions\ConditionsInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody(maxBytes: 262144)]
#[StrictRequest]
final readonly class SaveGroupConditionsRequestDto implements RequestDtoInterface
{
    /** @param list<ConditionProductRequestDto> $products */
    public function __construct(
        #[RouteParameter(name: 'group_id', pattern: ConditionsInputMapper::GROUP_ID_PATTERN, errorCode: 'NOT_FOUND', errorStatus: 404)]
        public string $groupId,
        public int $revision,
        public int $catalogRevision,
        public int $conditionsRevision,
        public bool $inherit,
        /** @var ConditionProductRequestDto[] */
        public array $products,
        public bool $giftEnabled,
        public int $giftThreshold,
        public bool $giftForStaff,
        #[RequestHeader('Idempotency-Key')]
        public string $idempotencyKey,
        #[RequestHeader('Authorization')]
        public string $authorization,
    ) {}
}
