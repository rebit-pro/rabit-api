<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\UseCase;

use Morefoto\Payment\Application\Payment\Dto\PaymentQuoteOutputDto;
use Morefoto\Payment\Application\Payment\Service\PaymentQuoteToken;
use Morefoto\Payment\Application\Payment\Service\PaymentSettings;
use Morefoto\Payment\Domain\Payment\Enum\AttemptStatusEnum;
use Morefoto\Payment\Domain\Payment\Enum\PaymentMethodEnum;
use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Service\PaymentAttemptPolicy;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\OrderPaymentInterface;

/** Показывает покупателю перед оплатой итог неизменяемого заказа, доступные способы и можно ли начать оплату.
 * Пересчёта нет (G1-DEC-01): токен фиксирует версию и сумму, которые покупатель подтверждает в PAY-01; нулевой итог не оплачивается.
 */
final readonly class GetPaymentQuoteUseCase
{
    public function __construct(
        private OrderPaymentInterface $orders,
        private PaymentAttemptRepositoryInterface $attempts,
        private PaymentAttemptPolicy $policy,
        private PaymentQuoteToken $tokens,
        private PaymentSettings $settings,
        private ClockInterface $clock,
    ) {}

    public function execute(?string $orderKey): PaymentQuoteOutputDto
    {
        $order = $this->orders->byKey($orderKey);
        $latest = $this->attempts->latest($order->id);
        $open = null !== $latest && AttemptStatusEnum::from($latest['STATUS'])->isOpen();

        return new PaymentQuoteOutputDto(
            subtotal: $order->subtotal,
            discount: $order->discount,
            giftSaving: $order->giftSaving,
            total: $order->total,
            quoteToken: $this->tokens->token($order),
            orderVersion: $order->version,
            canPay: $this->settings->enabled() && $this->policy->canStart($order->paymentStatus, $order->total, $order->closesAt, $this->clock->now(), $open),
            precedingAttemptId: $latest['PUBLIC_ID'] ?? null,
            activeAttemptId: $open ? $latest['PUBLIC_ID'] : null,
            paymentMethods: array_map(static fn(PaymentMethodEnum $method): string => $method->value, $this->settings->methods()),
        );
    }
}
