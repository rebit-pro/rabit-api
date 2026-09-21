<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Controller;

use Morefoto\Commerce\Application\Storefront\UseCase\CreateQuoteUseCase;
use Morefoto\Commerce\Application\Storefront\UseCase\GetStorefrontCatalogUseCase;
use Morefoto\Commerce\Presentation\Storefront\Dto\CreateQuoteRequestDto;
use Morefoto\Commerce\Presentation\Storefront\Dto\StorefrontRequestDto;
use Morefoto\Commerce\Presentation\Storefront\StorefrontMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;

final class StorefrontController extends PrivateApiJsonController
{
    public function __construct(private readonly GetStorefrontCatalogUseCase $catalog, private readonly CreateQuoteUseCase $quote, private readonly StorefrontMapper $mapper)
    {
        parent::__construct();
    }

    public function catalogAction(StorefrontRequestDto $request): ControllerJson
    {
        return $this->json($this->mapper->catalog($this->catalog->execute($request->token)));
    }

    public function quoteAction(CreateQuoteRequestDto $request): ControllerJson
    {
        return $this->json($this->mapper->quote($this->quote->execute($request->token, $this->mapper->lines($request)), $request->token));
    }
}
