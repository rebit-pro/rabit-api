<?php

declare(strict_types=1);

namespace Morefoto\Legal\Domain\Consent\Repository;

use Morefoto\Legal\Domain\Document\Entity\LegalDocumentVersion;
use Rebit\Share\Application\Contract\Consent\Enum\ConsentContextEnum;

interface ConsentJournalInterface
{
    /**
     * Повторная запись той же версии для того же субъекта не создаёт дубль.
     *
     * @param list<LegalDocumentVersion> $documents
     */
    public function add(ConsentContextEnum $context, int $subjectId, array $documents, \DateTimeImmutable $acceptedAt): void;

    /** @return array<string, list<string>> принятые версии по коду документа */
    public function acceptedVersions(ConsentContextEnum $context, int $subjectId): array;
}
