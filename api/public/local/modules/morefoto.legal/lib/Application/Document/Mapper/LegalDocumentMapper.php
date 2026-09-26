<?php

declare(strict_types=1);

namespace Morefoto\Legal\Application\Document\Mapper;

use Morefoto\Legal\Application\Document\Dto\LegalDocumentOutputDto;
use Morefoto\Legal\Domain\Document\Entity\LegalDocumentVersion;

final readonly class LegalDocumentMapper
{
    public function document(LegalDocumentVersion $version): LegalDocumentOutputDto
    {
        return new LegalDocumentOutputDto($version->code->value, $version->version, $version->title, $version->effectiveFrom);
    }

    /**
     * @param list<LegalDocumentVersion> $versions
     *
     * @return list<LegalDocumentOutputDto>
     */
    public function documents(array $versions): array
    {
        $documents = [];
        foreach ($versions as $version) {
            $documents[] = $this->document($version);
        }

        return $documents;
    }
}
