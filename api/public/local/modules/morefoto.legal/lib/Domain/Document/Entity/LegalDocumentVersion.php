<?php

declare(strict_types=1);

namespace Morefoto\Legal\Domain\Document\Entity;

use Morefoto\Legal\Domain\Document\Enum\LegalDocumentEnum;

/** Опубликованная редакция документа; reconsent — требует ли она повторного принятия от принявших прежнюю. */
final readonly class LegalDocumentVersion
{
    public function __construct(
        public LegalDocumentEnum $code,
        public string $version,
        public string $title,
        public string $effectiveFrom,
        public bool $reconsent,
    ) {}
}
