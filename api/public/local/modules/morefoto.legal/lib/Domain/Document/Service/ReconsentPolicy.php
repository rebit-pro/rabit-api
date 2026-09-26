<?php

declare(strict_types=1);

namespace Morefoto\Legal\Domain\Document\Service;

use Morefoto\Legal\Domain\Document\Entity\LegalDocumentVersion;

/** Решает, какие действующие редакции человек ещё должен принять, учитывая уже принятые.
 * Редакция без признака повторного принятия засчитывается тому, кто принял любую прежнюю редакцию того же документа:
 * так правка опечатки не заставляет всех соглашаться заново, а смена целей обработки — заставляет.
 */
final readonly class ReconsentPolicy
{
    /**
     * @param list<LegalDocumentVersion>  $current
     * @param array<string, list<string>> $accepted принятые версии по коду документа
     *
     * @return list<LegalDocumentVersion>
     */
    public function pending(array $current, array $accepted): array
    {
        $pending = [];
        foreach ($current as $document) {
            $versions = $accepted[$document->code->value] ?? [];
            if (in_array($document->version, $versions, true) || ([] !== $versions && !$document->reconsent)) {
                continue;
            }
            $pending[] = $document;
        }

        return $pending;
    }
}
