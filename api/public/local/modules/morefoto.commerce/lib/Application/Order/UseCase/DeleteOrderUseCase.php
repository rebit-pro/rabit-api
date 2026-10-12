<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\UseCase;

use Morefoto\Commerce\Application\Order\Contract\OrderStaffAccessInterface;
use Morefoto\Commerce\Application\Order\Contract\OrderTransactionInterface;
use Morefoto\Commerce\Domain\Order\Enum\PaymentStatusEnum;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Rebit\Share\Contracts\Payment\OrderPaymentRemovalInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Удаляет по просьбе организатора заказ, за который не поступало и уже не может поступить денег, — например тестовый
 * или брошенный покупателем. Оплаченный заказ и заказ с открытой оплатой остаются: это история денег и изготовления.
 */
final readonly class DeleteOrderUseCase
{
    public function __construct(
        private OrderStaffAccessInterface $access,
        private OrderRepository $orders,
        private OrderPaymentRemovalInterface $payments,
        private OrderTransactionInterface $transaction,
    ) {}

    public function execute(int $actorId, string $orderId): void
    {
        $this->transaction->execute(function() use ($actorId, $orderId): void {
            if ('organizer' !== $this->access->scope($actorId)->role) {
                throw new HttpException('FORBIDDEN', 403);
            }
            // The payment start and the reconciler take the same order lock, so no attempt appears after this check.
            /** @var array{ID: int|string, PAYMENT_STATUS: string}|false $order */
            $order = $this->orders->lockPublic($orderId)->fetch();
            if (false === $order) {
                throw new HttpException('ORDER_NOT_FOUND', 404);
            }
            match (PaymentStatusEnum::from($order['PAYMENT_STATUS'])) {
                PaymentStatusEnum::PAID => throw new HttpException('ORDER_PAID', 409),
                PaymentStatusEnum::PENDING => throw new HttpException('PAYMENT_IN_PROGRESS', 409),
                PaymentStatusEnum::UNPAID, PaymentStatusEnum::DECLINED => null,
            };
            $this->payments->remove((int)$order['ID']);
            $this->orders->delete((int)$order['ID']);
        });
    }
}
