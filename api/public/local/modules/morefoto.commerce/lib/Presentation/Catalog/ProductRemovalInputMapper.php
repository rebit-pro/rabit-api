<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Catalog;

use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductId;
use Morefoto\Commerce\Presentation\Catalog\Dto\DeleteProductRequestDto;

/** Stateless mapping of the product removal route to the product ID and the actor's session token. */
final readonly class ProductRemovalInputMapper
{
    public const string PRODUCT_ID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    public function productId(DeleteProductRequestDto $request): ProductId
    {
        return new ProductId($request->productId);
    }

    /** The catalogue guard re-resolves the token under its locks. */
    public function token(DeleteProductRequestDto $request): string
    {
        return substr($request->authorization, 7);
    }
}
