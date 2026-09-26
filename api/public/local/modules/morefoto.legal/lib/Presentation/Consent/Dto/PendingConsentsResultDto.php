<?php

declare(strict_types=1);

namespace Morefoto\Legal\Presentation\Consent\Dto;

use Morefoto\Legal\Presentation\Document\Dto\LegalDocumentResultDto;
use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class PendingConsentsResultDto implements ResultDtoInterface
{
    /** @param list<LegalDocumentResultDto> $documents */
    public function __construct(public array $documents) {}
}
