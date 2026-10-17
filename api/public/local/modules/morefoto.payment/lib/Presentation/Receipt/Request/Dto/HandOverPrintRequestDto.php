<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Receipt\Request\Dto;

use Morefoto\Payment\Presentation\Receipt\ReceiptInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody(maxBytes: 8192)]
#[StrictRequest]
final readonly class HandOverPrintRequestDto implements RequestDtoInterface
{
    /** @param list<string> $orderIds */
    public function __construct(
        /** @var string[] */
        public array $orderIds,
        public bool $confirmed,
        #[RouteParameter(name: 'group_id', pattern: ReceiptInputMapper::UUID_PATTERN, errorCode: 'GROUP_NOT_FOUND', errorStatus: 404)]
        public string $groupId,
    ) {}
}
