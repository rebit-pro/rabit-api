<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Storefront\Dto;

/** @phpstan-type QuoteLine array{
 * id:string, assignmentId:string, childCode:string, photoId:?string, productId:string, quantity:int,
 * product:array{id:string,name:string,description:string,kind:string,price:int,printCount:int,format:string,unit:string,staffDiscount:bool,active:bool},
 * photo:?array{id:string,assignmentId:string,code:string,width:int,height:int},
 * unitPrice:int,total:int,discount:int,coveredByGift:bool
 * }
 * @phpstan-type CartQuote array{lines:list<QuoteLine>,total:int,subtotal:int,discount:int,giftSaving:int,gifts:list<string>,count:int,invalid:list<string>,revision:int,conditionsRevision:int}
 */
final readonly class QuoteOutputDto
{
    /** @param CartQuote $quote */
    public function __construct(public array $quote, public string $quoteToken, public string $expiresAt) {}
}
