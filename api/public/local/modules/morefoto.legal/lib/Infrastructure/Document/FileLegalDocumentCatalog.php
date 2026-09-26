<?php

declare(strict_types=1);

namespace Morefoto\Legal\Infrastructure\Document;

use Morefoto\Legal\Domain\Document\Entity\LegalDocumentVersion;
use Morefoto\Legal\Domain\Document\Enum\LegalDocumentEnum;
use Morefoto\Legal\Domain\Document\Repository\LegalDocumentCatalogInterface;

/** Реестр редакций в documents/registry.php и тексты documents/<code>/<version>.md внутри модуля. */
final readonly class FileLegalDocumentCatalog implements LegalDocumentCatalogInterface
{
    private const string VERSION = '/^\d{4}-\d{2}-\d{2}(?:-\d{1,2})?$/D';

    public function __construct(private string $directory) {}

    public function current(LegalDocumentEnum $code): LegalDocumentVersion
    {
        $versions = $this->versions($code);

        return $versions[array_key_last($versions)];
    }

    public function versions(LegalDocumentEnum $code): array
    {
        /** @var array<string, array{
         *     title: string,
         *     versions: list<array{version: string, effectiveFrom: string, reconsent: bool}>,
         * }> $registry */
        $registry = require $this->directory . '/registry.php';
        $entry = $registry[$code->value] ?? throw new \UnexpectedValueException('Legal document is not registered: ' . $code->value);
        $versions = [];
        foreach ($entry['versions'] as $version) {
            if (1 !== preg_match(self::VERSION, $version['version'])) {
                throw new \UnexpectedValueException('Invalid legal document version: ' . $version['version']);
            }
            $versions[] = new LegalDocumentVersion($code, $version['version'], $entry['title'], $version['effectiveFrom'], $version['reconsent']);
        }
        if ([] === $versions) {
            throw new \UnexpectedValueException('Legal document has no versions: ' . $code->value);
        }

        return $versions;
    }

    public function text(LegalDocumentVersion $version): string
    {
        $path = $this->directory . '/' . $version->code->value . '/' . $version->version . '.md';
        $text = is_file($path) ? file_get_contents($path) : false;
        if (false === $text) {
            throw new \RuntimeException('Legal document text is missing: ' . $version->code->value . ' ' . $version->version);
        }

        return $text;
    }
}
