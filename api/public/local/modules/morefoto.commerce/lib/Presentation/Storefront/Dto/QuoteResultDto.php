<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Storefront\Dto;

use Morefoto\Commerce\Application\Storefront\Dto\QuoteOutputDto;
use Rebit\Share\Shared\Interface\ResponseDtoInterface;

/** @phpstan-import-type CartQuote from QuoteOutputDto */
final readonly class QuoteResultDto implements ResponseDtoInterface
{
    /** @param CartQuote $quote */
    public function __construct(public array $quote, public string $quoteToken, public string $expiresAt) {}
}
