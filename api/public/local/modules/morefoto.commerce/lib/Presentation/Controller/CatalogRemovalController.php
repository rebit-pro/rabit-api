<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Controller;

use Morefoto\Commerce\Application\Catalog\UseCase\DeleteProductUseCase;
use Morefoto\Commerce\Presentation\Catalog\Dto\DeleteProductRequestDto;
use Morefoto\Commerce\Presentation\Catalog\ProductRemovalInputMapper;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\EmptyResponse;

/** Product removal from the catalogue (#92). */
final class CatalogRemovalController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly DeleteProductUseCase $delete,
        private readonly ProductRemovalInputMapper $input,
    ) {
        parent::__construct();
    }

    public function deleteAction(DeleteProductRequestDto $request): EmptyResponse
    {
        $this->delete->execute($this->getAuthUserId(), $this->input->token($request), $this->input->productId($request));

        return $this->noContent();
    }
}
