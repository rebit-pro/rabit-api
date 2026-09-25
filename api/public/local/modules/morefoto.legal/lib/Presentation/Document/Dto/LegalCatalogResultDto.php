<?php

declare(strict_types=1);

namespace Morefoto\Legal\Presentation\Document\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class LegalCatalogResultDto implements ResultDtoInterface
{
    /** @param list<LegalDocumentResultDto> $documents */
    public function __construct(
        public array $documents,
        public SellerResultDto $seller,
    ) {}
}
