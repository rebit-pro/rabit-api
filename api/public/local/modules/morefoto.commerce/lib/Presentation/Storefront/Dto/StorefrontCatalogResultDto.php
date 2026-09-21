<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Storefront\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class StorefrontCatalogResultDto implements ResponseDtoInterface
{
    /** @param list<array{id:string,name:string,description:string,kind:string,price:int,printCount:int,format:string,unit:string,staffDiscount:bool,active:bool}> $products
     * @param array{receiptChannels:list<string>,purchaseEnabled:bool} $capabilities
     */
    public function __construct(
        public array $products,
        public int $giftThreshold,
        public bool $giftForStaff,
        public int $revision,
        public int $conditionsRevision,
        public array $capabilities,
        public ?string $purchaseTerms,
    ) {}
}
