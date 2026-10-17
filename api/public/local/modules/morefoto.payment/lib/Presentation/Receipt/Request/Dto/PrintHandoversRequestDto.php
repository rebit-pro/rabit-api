<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Receipt\Request\Dto;

use Morefoto\Payment\Presentation\Receipt\ReceiptInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class PrintHandoversRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'group_id', pattern: ReceiptInputMapper::UUID_PATTERN, errorCode: 'GROUP_NOT_FOUND', errorStatus: 404)]
        public string $groupId,
    ) {}
}
