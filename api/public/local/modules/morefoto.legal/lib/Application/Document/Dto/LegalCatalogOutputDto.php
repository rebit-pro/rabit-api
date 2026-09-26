<?php

declare(strict_types=1);

namespace Morefoto\Legal\Application\Document\Dto;

final readonly class LegalCatalogOutputDto
{
    /** @param list<LegalDocumentOutputDto> $documents */
    public function __construct(
        public array $documents,
        public SellerOutputDto $seller,
    ) {}
}
