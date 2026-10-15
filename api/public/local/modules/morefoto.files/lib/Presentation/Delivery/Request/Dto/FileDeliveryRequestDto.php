<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Delivery\Request\Dto;

use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class FileDeliveryRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'file_delivery_id', pattern: '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', errorCode: 'DELIVERY_NOT_FOUND', errorStatus: 404)]
        public string $deliveryId,
        #[RequestHeader('X-Order-Key', required: false)]
        public ?string $orderKey = null,
    ) {}
}
