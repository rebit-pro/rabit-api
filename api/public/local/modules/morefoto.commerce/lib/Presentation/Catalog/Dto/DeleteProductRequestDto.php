<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Catalog\Dto;

use Morefoto\Commerce\Presentation\Catalog\ProductRemovalInputMapper;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[StrictRequest]
final readonly class DeleteProductRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'product_id', pattern: ProductRemovalInputMapper::PRODUCT_ID_PATTERN, errorCode: 'NOT_FOUND', errorStatus: 404)]
        public string $productId,
        #[RequestHeader(name: 'Authorization')]
        public string $authorization,
    ) {}
}
