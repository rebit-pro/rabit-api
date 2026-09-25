<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Storefront\Service;

use Morefoto\Commerce\Application\Conditions\Service\PublishedPrices;
use Morefoto\Commerce\Application\Conditions\UseCase\GetGroupConditionsUseCase;
use Morefoto\Commerce\Application\Catalog\Dto\ProductOutputDto;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteLineInputDto;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteOutputDto;
use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Conditions\Service\SalesPolicy;
use Morefoto\Commerce\Domain\Conditions\ValueObject\SalesProduct;
use Morefoto\Commerce\Domain\Conditions\ValueObject\SaleItem;
use Rebit\Share\Contracts\Media\Dto\GalleryAccessOutputDto;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Contracts\Handoff\StaffEligibilityInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Проверяет состав корзины по разрешённым назначениям и рассчитывает цены правилами E3 от цены для покупателя (E6).
 * Связывает итог с версиями снимков, условиями группы и серверным подтверждением льготы.
 *
 * @phpstan-import-type CartQuote from QuoteOutputDto
 * @phpstan-import-type QuoteLine from QuoteOutputDto
 */
final readonly class StorefrontQuote
{
    public function __construct(
        private GalleryAccessInterface $gallery,
        private GetGroupConditionsUseCase $conditions,
        private StaffEligibilityInterface $staff,
        private PublishedPrices $prices,
    ) {}

    /** @param list<QuoteLineInputDto> $lines
     * @return array{quote:CartQuote,fingerprint:string,gallery:GalleryAccessOutputDto}
     */
    public function calculate(string $token, array $lines): array
    {
        if (100 < count($lines)) {
            throw new HttpException('INVALID_CART', 422);
        }
        $gallery = $this->gallery->resolve($token);
        if ('open' !== $gallery->state) {
            throw new HttpException('GALLERY_CLOSED', 409);
        }
        $conditions = $this->conditions->executeWithinTransaction($gallery->group->id);
        $photos = [];
        $mediaFingerprint = [];
        foreach ($gallery->assignments as $photo) {
            $photos[$photo->assignmentId] = $photo;
            $mediaFingerprint[] = [$photo->assignmentId, $photo->childId, $photo->photoId, $photo->revision];
        }
        $products = [];
        $pricing = [];
        foreach ($this->prices->publish($conditions) as $product) {
            $products[$product->id] = $product;
            $pricing[] = new SalesProduct($product->id, $product->kind, $product->price, $product->active, $product->staffDiscount);
        }
        $items = [];
        $selected = [];
        $children = [];
        $normalized = [];
        foreach ($lines as $line) {
            $photo = $photos[$line->assignmentId] ?? null;
            $product = $products[$line->productId] ?? null;
            if (null === $photo || null === $product || !$product->active || 1 > $line->quantity || 99 < $line->quantity
                || (ProductKind::PHYSICAL !== $product->kind && 1 !== $line->quantity)) {
                throw new HttpException('INVALID_CART', 422);
            }
            $id = (ProductKind::BUNDLE === $product->kind ? $photo->childId : $photo->assignmentId) . ':' . $product->id;
            if (isset($selected[$id])) {
                throw new HttpException('DUPLICATE_CART_LINE', 422);
            }
            $selected[$id] = $photo;
            $children[$photo->nativeChildId] = true;
            $normalized[$id] = [$line->assignmentId, $line->productId, $line->quantity];
            $items[] = new SaleItem($id, $photo->childCode, ProductKind::BUNDLE === $product->kind ? null : $photo->photoId, $line->productId, $line->quantity);
        }
        $bundled = [];
        foreach ($items as $item) {
            if (ProductKind::BUNDLE === $products[$item->productId->value]->kind) {
                $bundled[$item->childId] = true;
            }
        }
        foreach ($items as $item) {
            if (ProductKind::DIGITAL === $products[$item->productId->value]->kind && isset($bundled[$item->childId])) {
                throw new HttpException('DIGITAL_ALREADY_IN_BUNDLE', 422);
            }
        }
        $confirmedStaff = 'staff' === $gallery->group->kind;
        $eligible = $confirmedStaff ? $this->staff->confirmed($gallery->group->shootId, array_keys($children)) : [];
        if ($confirmedStaff) {
            foreach ($children as $id => $_) {
                if (true !== ($eligible[$id] ?? false)) {
                    throw new HttpException('STAFF_ELIGIBILITY_REQUIRED', 403);
                }
            }
        }
        $policy = new SalesPolicy($conditions->catalogRevision, $conditions->conditionsRevision, $pricing, $conditions->giftThreshold, $conditions->giftForStaff);
        $priced = $policy->quote($items, $confirmedStaff);
        if ([] !== $priced->invalidItemIds) {
            throw new HttpException('INVALID_CART', 422);
        }
        $output = [];
        $count = 0;
        foreach ($priced->items as $item) {
            $photo = $selected[$item->item->id];
            $output[] = [
                'id' => $item->item->id, 'assignmentId' => $photo->assignmentId,
                'childCode' => $photo->childCode, 'photoId' => $item->item->photoId, 'productId' => $item->product->id->value,
                'quantity' => $item->quantity, 'product' => $this->product($products[$item->product->id->value]),
                'photo' => null === $item->item->photoId ? null : [
                    'id' => $photo->photoId, 'assignmentId' => $photo->assignmentId, 'code' => $photo->code,
                    'width' => $photo->width, 'height' => $photo->height,
                ],
                'unitPrice' => $item->unitPrice, 'total' => $item->total, 'discount' => $item->staffDiscount, 'coveredByGift' => $item->coveredByGift,
            ];
            $count += $item->quantity;
        }
        ksort($normalized);
        ksort($eligible);
        $fingerprint = hash('sha256', json_encode([
            $normalized, $mediaFingerprint, $gallery->group, $gallery->capabilityRevision, $conditions, $eligible,
        ], JSON_THROW_ON_ERROR));

        return ['quote' => [
            'lines' => $output, 'total' => $priced->total, 'subtotal' => $priced->subtotal, 'discount' => $priced->staffDiscount,
            'giftSaving' => $priced->giftSaving, 'gifts' => array_keys($priced->gifts), 'count' => $count, 'invalid' => [],
            'revision' => $conditions->catalogRevision, 'conditionsRevision' => $conditions->conditionsRevision,
        ], 'fingerprint' => $fingerprint, 'gallery' => $gallery];
    }

    /** @return array{id:string,name:string,description:string,kind:string,price:int,printCount:int,format:string,unit:string,staffDiscount:bool,active:bool} */
    public function product(ProductOutputDto $product): array
    {
        return [
            'id' => $product->id, 'name' => $product->name, 'description' => $product->description, 'kind' => $product->kind->value,
            'price' => $product->price, 'printCount' => $product->printCount, 'format' => $product->format, 'unit' => $product->unit,
            'staffDiscount' => $product->staffDiscount, 'active' => $product->active,
        ];
    }
}
