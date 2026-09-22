<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class CurrentOrderRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RequestHeader('X-Order-Key', required: false)]
        public ?string $orderKey = null,
    ) {}
}
