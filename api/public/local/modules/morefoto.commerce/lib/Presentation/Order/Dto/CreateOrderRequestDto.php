<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order\Dto;

use Morefoto\Commerce\Presentation\Storefront\Dto\QuoteLineRequestDto;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\JsonBody;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

#[JsonBody]
#[StrictRequest]
final readonly class CreateOrderRequestDto implements RequestDtoInterface
{
    /** @param list<QuoteLineRequestDto> $lines */
    public function __construct(
        #[RouteParameter(name: 'gallery_token', pattern: '/^[a-f0-9]{64}$/D', errorCode: 'GALLERY_NOT_FOUND', errorStatus: 404)]
        public string $token,
        /** @var QuoteLineRequestDto[] */
        public array $lines,
        public OrderBuyerRequestDto $buyer,
        public string $quoteToken,
        #[RequestHeader('Idempotency-Key')]
        public string $idempotencyKey,
    ) {}
}
