<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Storefront\UseCase;

use Morefoto\Commerce\Application\Conditions\UseCase\GetGroupConditionsUseCase;
use Morefoto\Commerce\Application\Storefront\Dto\CatalogOutputDto;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Возвращает действующие товары и условия группы после проверки приватной ссылки. */
final readonly class GetStorefrontCatalogUseCase
{
    public function __construct(private GalleryAccessInterface $gallery, private GetGroupConditionsUseCase $conditions) {}

    public function execute(string $token): CatalogOutputDto
    {
        $gallery = $this->gallery->context($token);
        if ('preparing' === $gallery->state) {
            throw new HttpException('GALLERY_NOT_READY', 409);
        }
        $conditions = $this->conditions->execute($gallery->group->id);
        $products = [];
        foreach ($conditions->products as $product) {
            if ($product->active) {
                $products[] = $product;
            }
        }

        return new CatalogOutputDto($products, $conditions->giftThreshold, $conditions->giftForStaff, $conditions->catalogRevision, $conditions->conditionsRevision);
    }
}
