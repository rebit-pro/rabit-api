<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

/**
 * @phpstan-type OrderQuoteLine array{
 *     id: string, assignmentId: string, childCode: string, photoId: null|string, productId: string, quantity: int,
 *     product: array{id: string, name: string, description: string, kind: string, price: int, printCount: int, format: string, unit: string, staffDiscount: bool, active: bool},
 *     photo: null|array{id: string, assignmentId: string, code: string, width: int, height: int},
 *     unitPrice: int, total: int, discount: int, coveredByGift: bool,
 * }
 * @phpstan-type OrderQuote array{
 *     lines: list<OrderQuoteLine>, total: int, subtotal: int, discount: int, giftSaving: int, gifts: list<string>,
 *     count: int, invalid: list<string>, revision: int, conditionsRevision: int,
 * }
 */
final readonly class OrderOutputDto
{
    /** @param OrderQuote $quote */
    public function __construct(
        public string $id,
        public string $number,
        public string $institutionId,
        public string $institutionName,
        public string $shootId,
        public string $shootName,
        public string $groupId,
        public string $groupName,
        public string $audience,
        public string $createdAt,
        public OrderBuyerOutputDto $buyer,
        public array $quote,
        public string $paymentStatus,
        public string $productionStatus,
        public string $version,
        /** Момент подтверждённой оплаты в бизнес-времени; null до оплаты. */
        public ?string $paidAt = null,
        public bool $latePayment = false,
    ) {}
}
