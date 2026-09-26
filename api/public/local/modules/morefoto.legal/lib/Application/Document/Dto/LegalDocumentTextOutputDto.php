<?php

declare(strict_types=1);

namespace Morefoto\Legal\Application\Document\Dto;

final readonly class LegalDocumentTextOutputDto
{
    /**
     * @param list<DocumentBlockOutputDto> $blocks
     * @param list<LegalDocumentOutputDto> $versions все редакции, от новой к старой
     */
    public function __construct(
        public LegalDocumentOutputDto $document,
        public bool $current,
        public array $blocks,
        public array $versions,
    ) {}
}
