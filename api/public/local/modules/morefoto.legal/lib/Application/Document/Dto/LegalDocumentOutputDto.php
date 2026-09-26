<?php

declare(strict_types=1);

namespace Morefoto\Legal\Application\Document\Dto;

final readonly class LegalDocumentOutputDto
{
    public function __construct(
        public string $code,
        public string $version,
        public string $title,
        public string $effectiveFrom,
    ) {}
}
