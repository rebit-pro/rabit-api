<?php

declare(strict_types=1);

namespace Morefoto\Legal\Presentation\Document\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class LegalDocumentTextResultDto implements ResultDtoInterface
{
    /**
     * @param list<DocumentBlockResultDto> $blocks
     * @param list<LegalDocumentResultDto> $versions
     */
    public function __construct(
        public LegalDocumentResultDto $document,
        public bool $current,
        public array $blocks,
        public array $versions,
    ) {}
}
