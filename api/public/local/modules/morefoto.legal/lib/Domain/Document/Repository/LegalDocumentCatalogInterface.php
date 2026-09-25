<?php

declare(strict_types=1);

namespace Morefoto\Legal\Domain\Document\Repository;

use Morefoto\Legal\Domain\Document\Entity\LegalDocumentVersion;
use Morefoto\Legal\Domain\Document\Enum\LegalDocumentEnum;

interface LegalDocumentCatalogInterface
{
    public function current(LegalDocumentEnum $code): LegalDocumentVersion;

    /** @return list<LegalDocumentVersion> от старой к действующей */
    public function versions(LegalDocumentEnum $code): array;

    /** Исходный текст редакции с подстановками вида {{seller.name}}. */
    public function text(LegalDocumentVersion $version): string;
}
