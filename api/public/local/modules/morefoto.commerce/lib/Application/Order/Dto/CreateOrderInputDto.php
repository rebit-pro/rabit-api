<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

use Morefoto\Commerce\Application\Storefront\Dto\QuoteLineInputDto;
use Rebit\Share\Application\Contract\Consent\Dto\AcceptedDocumentDto;

final readonly class CreateOrderInputDto
{
    /**
     * @param list<QuoteLineInputDto>   $lines
     * @param list<AcceptedDocumentDto> $consents принятые покупателем редакции согласия и оферты
     */
    public function __construct(public string $quoteToken, public array $lines, public OrderBuyerInputDto $buyer, public array $consents) {}
}
