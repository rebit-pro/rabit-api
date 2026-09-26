<?php

declare(strict_types=1);

namespace Morefoto\Legal\Presentation\Controller;

use Morefoto\Legal\Application\Document\UseCase\GetLegalDocumentUseCase;
use Morefoto\Legal\Application\Document\UseCase\ListLegalDocumentsUseCase;
use Morefoto\Legal\Presentation\Document\Dto\LegalCatalogRequestDto;
use Morefoto\Legal\Presentation\Document\Dto\LegalDocumentRequestDto;
use Morefoto\Legal\Presentation\Document\Dto\LegalDocumentVersionRequestDto;
use Morefoto\Legal\Presentation\LegalResultMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;

/** Публичные юридические документы и реквизиты продавца: доступны без входа и без ссылки на группу. */
final class LegalDocumentController extends PrivateApiJsonController
{
    public function __construct(
        private readonly ListLegalDocumentsUseCase $list,
        private readonly GetLegalDocumentUseCase $document,
        private readonly LegalResultMapper $result,
    ) {
        parent::__construct();
    }

    public function listAction(LegalCatalogRequestDto $request): ControllerJson
    {
        return $this->json($this->result->catalog($this->list->execute()));
    }

    public function documentAction(LegalDocumentRequestDto $request): ControllerJson
    {
        return $this->json($this->result->text($this->document->execute($request->code, null)));
    }

    public function versionAction(LegalDocumentVersionRequestDto $request): ControllerJson
    {
        return $this->json($this->result->text($this->document->execute($request->code, $request->version)));
    }
}
