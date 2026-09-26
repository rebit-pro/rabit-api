<?php

declare(strict_types=1);

namespace Morefoto\Legal\Application\Document\UseCase;

use Morefoto\Legal\Application\Document\Contract\SellerProviderInterface;
use Morefoto\Legal\Application\Document\Dto\LegalDocumentTextOutputDto;
use Morefoto\Legal\Application\Document\Mapper\LegalDocumentMapper;
use Morefoto\Legal\Application\Document\Service\LegalTextRenderer;
use Morefoto\Legal\Domain\Document\Enum\LegalDocumentEnum;
use Morefoto\Legal\Domain\Document\Repository\LegalDocumentCatalogInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Показывает текст действующей или архивной редакции документа с подставленными реквизитами продавца.
 * Архивные редакции остаются доступными, чтобы человек видел условия, которые принимал раньше.
 */
final readonly class GetLegalDocumentUseCase
{
    public function __construct(
        private LegalDocumentCatalogInterface $catalog,
        private SellerProviderInterface $seller,
        private LegalTextRenderer $renderer,
        private LegalDocumentMapper $mapper,
    ) {}

    /** @throws HttpException DOCUMENT_NOT_FOUND */
    public function execute(string $code, ?string $version): LegalDocumentTextOutputDto
    {
        $document = LegalDocumentEnum::tryFrom($code) ?? throw new HttpException('DOCUMENT_NOT_FOUND', 404);
        $versions = $this->catalog->versions($document);
        $selected = null;
        foreach ($versions as $candidate) {
            if (null === $version || $candidate->version === $version) {
                $selected = $candidate;
            }
        }
        if (null === $selected) {
            throw new HttpException('DOCUMENT_NOT_FOUND', 404);
        }

        return new LegalDocumentTextOutputDto(
            $this->mapper->document($selected),
            $selected === $versions[array_key_last($versions)],
            $this->renderer->render($this->catalog->text($selected), $this->seller->seller()),
            array_reverse($this->mapper->documents($versions)),
        );
    }
}
