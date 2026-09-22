<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Storefront\Dto;

use Rebit\Share\Contracts\Media\Dto\GalleryAccessOutputDto;

final readonly class ValidatedQuoteOutputDto
{
    public function __construct(public QuoteOutputDto $quote, public GalleryAccessOutputDto $gallery) {}
}
