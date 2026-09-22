<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class StaffOrderDetailRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'order_id', pattern: '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', errorCode: 'ORDER_NOT_FOUND', errorStatus: 404)]
        public string $orderId,
    ) {}
}
