<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

use Morefoto\Commerce\Application\Storefront\Dto\QuoteLineInputDto;

final readonly class CreateOrderInputDto
{
    /** @param list<QuoteLineInputDto> $lines */
    public function __construct(public string $quoteToken, public array $lines, public OrderBuyerInputDto $buyer) {}
}
