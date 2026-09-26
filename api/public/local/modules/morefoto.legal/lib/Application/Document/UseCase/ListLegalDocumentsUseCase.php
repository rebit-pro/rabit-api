<?php

declare(strict_types=1);

namespace Morefoto\Legal\Application\Document\UseCase;

use Morefoto\Legal\Application\Document\Contract\SellerProviderInterface;
use Morefoto\Legal\Application\Document\Dto\LegalCatalogOutputDto;
use Morefoto\Legal\Application\Document\Mapper\LegalDocumentMapper;
use Morefoto\Legal\Domain\Document\Enum\LegalDocumentEnum;
use Morefoto\Legal\Domain\Document\Repository\LegalDocumentCatalogInterface;

/** Отдаёт действующие редакции всех документов и реквизиты продавца для футера, страниц и форм согласия.
 * По этим версиям клиент отправляет согласие, поэтому сервер остаётся единственным источником версий.
 */
final readonly class ListLegalDocumentsUseCase
{
    public function __construct(
        private LegalDocumentCatalogInterface $catalog,
        private SellerProviderInterface $seller,
        private LegalDocumentMapper $mapper,
    ) {}

    public function execute(): LegalCatalogOutputDto
    {
        $documents = [];
        foreach (LegalDocumentEnum::cases() as $code) {
            $documents[] = $this->mapper->document($this->catalog->current($code));
        }

        return new LegalCatalogOutputDto($documents, $this->seller->seller());
    }
}
