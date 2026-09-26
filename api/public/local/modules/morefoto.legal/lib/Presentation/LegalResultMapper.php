<?php

declare(strict_types=1);

namespace Morefoto\Legal\Presentation;

use Morefoto\Legal\Application\Document\Dto\LegalCatalogOutputDto;
use Morefoto\Legal\Application\Document\Dto\LegalDocumentOutputDto;
use Morefoto\Legal\Application\Document\Dto\LegalDocumentTextOutputDto;
use Morefoto\Legal\Presentation\Consent\Dto\PendingConsentsResultDto;
use Morefoto\Legal\Presentation\Document\Dto\DocumentBlockResultDto;
use Morefoto\Legal\Presentation\Document\Dto\LegalCatalogResultDto;
use Morefoto\Legal\Presentation\Document\Dto\LegalDocumentResultDto;
use Morefoto\Legal\Presentation\Document\Dto\LegalDocumentTextResultDto;
use Morefoto\Legal\Presentation\Document\Dto\SellerResultDto;

final readonly class LegalResultMapper
{
    public function catalog(LegalCatalogOutputDto $catalog): LegalCatalogResultDto
    {
        $seller = $catalog->seller;

        return new LegalCatalogResultDto(
            $this->documents($catalog->documents),
            new SellerResultDto($seller->published, $seller->name, $seller->inn, $seller->ogrnip, $seller->address, $seller->email, $seller->phone),
        );
    }

    public function text(LegalDocumentTextOutputDto $text): LegalDocumentTextResultDto
    {
        $blocks = [];
        foreach ($text->blocks as $block) {
            $blocks[] = new DocumentBlockResultDto($block->type, $block->text, $block->level, $block->items);
        }

        return new LegalDocumentTextResultDto($this->document($text->document), $text->current, $blocks, $this->documents($text->versions));
    }

    /** @param list<LegalDocumentOutputDto> $documents */
    public function pending(array $documents): PendingConsentsResultDto
    {
        return new PendingConsentsResultDto($this->documents($documents));
    }

    private function document(LegalDocumentOutputDto $document): LegalDocumentResultDto
    {
        return new LegalDocumentResultDto($document->code, $document->version, $document->title, $document->effectiveFrom);
    }

    /**
     * @param list<LegalDocumentOutputDto> $documents
     *
     * @return list<LegalDocumentResultDto>
     */
    private function documents(array $documents): array
    {
        $results = [];
        foreach ($documents as $document) {
            $results[] = $this->document($document);
        }

        return $results;
    }
}
