<?php

declare(strict_types=1);

namespace Morefoto\Legal\Tests\Unit;

use Morefoto\Legal\Domain\Consent\Repository\ConsentJournalInterface;
use Rebit\Share\Application\Contract\Consent\Enum\ConsentContextEnum;

final class InMemoryConsentJournal implements ConsentJournalInterface
{
    /** @var array<string, true> */
    public array $rows = [];

    public function add(ConsentContextEnum $context, int $subjectId, array $documents, \DateTimeImmutable $acceptedAt): void
    {
        foreach ($documents as $document) {
            $this->rows[$context->value . ':' . $subjectId . ':' . $document->code->value . ':' . $document->version] = true;
        }
    }

    public function acceptedVersions(ConsentContextEnum $context, int $subjectId): array
    {
        $accepted = [];
        foreach (array_keys($this->rows) as $row) {
            [$rowContext, $rowSubject, $code, $version] = explode(':', $row);
            if ($rowContext === $context->value && (int)$rowSubject === $subjectId) {
                $accepted[$code][] = $version;
            }
        }

        return $accepted;
    }
}
