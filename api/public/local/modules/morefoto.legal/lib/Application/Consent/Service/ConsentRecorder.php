<?php

declare(strict_types=1);

namespace Morefoto\Legal\Application\Consent\Service;

use Morefoto\Legal\Domain\Consent\Repository\ConsentJournalInterface;
use Morefoto\Legal\Domain\Document\Entity\LegalDocumentVersion;
use Morefoto\Legal\Domain\Document\Repository\LegalDocumentCatalogInterface;
use Morefoto\Legal\Domain\Document\Service\ConsentRequirementPolicy;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\Consent\ConsentRecorderInterface;
use Rebit\Share\Application\Contract\Consent\Enum\ConsentContextEnum;
use Rebit\Share\Shared\Exception\HttpException;

/** Записывает в журнал, что человек принял действующие редакции документов своего сценария.
 * Устаревшая или пропущенная редакция отклоняется с перечнем действующих версий, чтобы клиент показал актуальный текст.
 */
final readonly class ConsentRecorder implements ConsentRecorderInterface
{
    public function __construct(
        private LegalDocumentCatalogInterface $catalog,
        private ConsentRequirementPolicy $requirements,
        private ConsentJournalInterface $journal,
        private ClockInterface $clock,
    ) {}

    public function record(ConsentContextEnum $context, int $subjectId, array $accepted): void
    {
        $given = [];
        foreach ($accepted as $document) {
            $given[$document->code] = $document->version;
        }
        $required = $this->required($context);
        foreach ($required as $document) {
            if (($given[$document->code->value] ?? null) !== $document->version) {
                throw new HttpException('CONSENT_REQUIRED', 422, null, ['documents' => $this->references($required)]);
            }
        }
        $this->journal->add($context, $subjectId, $required, $this->clock->now());
    }

    /** @return list<LegalDocumentVersion> */
    public function required(ConsentContextEnum $context): array
    {
        $documents = [];
        foreach ($this->requirements->documents($context) as $code) {
            $documents[] = $this->catalog->current($code);
        }

        return $documents;
    }

    /**
     * @param list<LegalDocumentVersion> $documents
     *
     * @return list<array{code: string, version: string}>
     */
    private function references(array $documents): array
    {
        $references = [];
        foreach ($documents as $document) {
            $references[] = ['code' => $document->code->value, 'version' => $document->version];
        }

        return $references;
    }
}
