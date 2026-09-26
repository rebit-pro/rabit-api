<?php

declare(strict_types=1);

namespace Morefoto\Legal\Presentation\Document\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class LegalDocumentResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $code,
        public string $version,
        public string $title,
        public string $effectiveFrom,
    ) {}
}
