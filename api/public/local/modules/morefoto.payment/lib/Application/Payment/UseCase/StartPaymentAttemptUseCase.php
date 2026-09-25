<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\UseCase;

use Morefoto\Payment\Application\Payment\Contract\PaymentIdGeneratorInterface;
use Morefoto\Payment\Application\Payment\Contract\PaymentProviderInterface;
use Morefoto\Payment\Application\Payment\Contract\PaymentTransactionInterface;
use Morefoto\Payment\Application\Payment\Dto\PaymentAttemptOutputDto;
use Morefoto\Payment\Application\Payment\Dto\StartPaymentInputDto;
use Morefoto\Payment\Application\Payment\Mapper\PaymentOutputMapper;
use Morefoto\Payment\Application\Payment\Service\PaymentQuoteToken;
use Morefoto\Payment\Application\Payment\Service\PaymentReconciler;
use Morefoto\Payment\Application\Payment\Service\PaymentSettings;
use Morefoto\Payment\Domain\Payment\Enum\AttemptStatusEnum;
use Morefoto\Payment\Domain\Payment\Enum\ConfirmationSourceEnum;
use Morefoto\Payment\Domain\Payment\Enum\PaymentMethodEnum;
use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Service\PaymentAttemptPolicy;
use Morefoto\Payment\Domain\Payment\ValueObject\IdempotencyKey;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\Dto\OrderPaymentInputDto;
use Rebit\Share\Contracts\Commerce\OrderPaymentInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Начинает оплату заказа выбранным способом: резервирует единственную открытую попытку под блокировкой заказа,
 * затем вне транзакции создаёт платёж у провайдера. Повтор, двойной клик и вторая вкладка получают ту же попытку;
 * браузер не может объявить заказ оплаченным.
 */
final readonly class StartPaymentAttemptUseCase
{
    public function __construct(
        private OrderPaymentInterface $orders,
        private PaymentAttemptRepositoryInterface $attempts,
        private PaymentReconciler $reconciler,
        private PaymentProviderInterface $provider,
        private PaymentTransactionInterface $transaction,
        private PaymentAttemptPolicy $policy,
        private PaymentQuoteToken $tokens,
        private PaymentSettings $settings,
        private PaymentIdGeneratorInterface $ids,
        private PaymentOutputMapper $mapper,
        private ClockInterface $clock,
    ) {}

    public function execute(?string $orderKey, IdempotencyKey $key, StartPaymentInputDto $input): PaymentAttemptOutputDto
    {
        $order = $this->orders->byKey($orderKey);
        if (!$this->settings->enabled()) {
            throw new HttpException('PAYMENT_DISABLED', 403);
        }
        $method = PaymentMethodEnum::tryFrom($input->paymentMethod);
        if (null === $method || !in_array($method, $this->settings->methods(), true)) {
            throw new HttpException('PAYMENT_METHOD_UNAVAILABLE', 422);
        }
        $requestHash = hash('sha256', implode("\n", [$input->orderVersion, $input->precedingAttemptId ?? '', $input->quoteToken, $method->value]));
        /** @var array{int, bool} $reserved */
        $reserved = $this->transaction->execute(fn(): array => $this->reserve($order->id, $key, $requestHash, $input, $method));
        [$attemptId, $created] = $reserved;
        if ($created) {
            $this->reconciler->reconcile($attemptId, ConfirmationSourceEnum::START);
        }
        $attempt = $this->attempts->find($attemptId);
        if (null === $attempt) {
            throw new \LogicException('A reserved payment attempt disappeared.');
        }

        return $this->mapper->attempt($attempt, $this->orders->byKey($orderKey)->version, $created);
    }

    /** @return array{int, bool} ID попытки и признак новой попытки */
    private function reserve(int $orderId, IdempotencyKey $key, string $requestHash, StartPaymentInputDto $input, PaymentMethodEnum $method): array
    {
        $order = $this->orders->lock($orderId);
        $clientKeyHash = hash('sha256', $key->value);
        $replay = $this->attempts->findByClientKey($orderId, $clientKeyHash);
        if (null !== $replay) {
            if ($replay['REQUEST_HASH'] !== $requestHash) {
                throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
            }

            return [$replay['ID'], false];
        }
        $latest = $this->attempts->latest($orderId);
        if (null !== $latest && AttemptStatusEnum::from($latest['STATUS'])->isOpen()) {
            return [$latest['ID'], false];
        }
        $now = $this->clock->now();
        $this->policy->assertCanStart($order->paymentStatus, $order->closesAt, $now);
        if ($input->orderVersion !== $order->version || $input->quoteToken !== $this->tokens->token($order)) {
            throw new HttpException('QUOTE_CHANGED', 409);
        }
        if ($input->precedingAttemptId !== ($latest['PUBLIC_ID'] ?? null)) {
            throw new HttpException('ATTEMPT_CONFLICT', 409);
        }
        $createdAt = $this->policy->utc($now);
        $id = $this->attempts->insert([
            'PUBLIC_ID' => $this->ids->uuid(),
            'ORDER_ID' => $order->id,
            'ORDER_PUBLIC_ID' => $order->publicId,
            'ORDER_NUMBER' => $order->number,
            'ORDER_VERSION' => $order->version,
            'INSTITUTION_ID' => $order->institutionId,
            'INSTITUTION_NAME' => $order->institutionName,
            'GROUP_NAME' => $order->groupName,
            'AMOUNT' => $order->total,
            'PAYMENT_METHOD' => $method->value,
            'PROVIDER' => $this->provider->code(),
            'SHOP_ID' => $this->provider->shopId(),
            'PRECEDING_ID' => $latest['ID'] ?? null,
            'CLIENT_KEY_HASH' => $clientKeyHash,
            'REQUEST_HASH' => $requestHash,
            'PROVIDER_KEY' => $this->ids->uuid(),
            // A crash before the provider call is picked up by the scheduled check with the same provider key.
            'NEXT_CHECK_AT' => $this->policy->nextCheckAt(AttemptStatusEnum::UNKNOWN, 1, $now) ?? $createdAt,
            'CREATED_AT' => $createdAt,
        ]);
        $this->orders->applyPayment(new OrderPaymentInputDto($order->id, 'pending'));

        return [$id, true];
    }
}
