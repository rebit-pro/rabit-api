<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\UseCase;

use Morefoto\Payment\Application\Payment\Dto\PaymentAttemptOutputDto;
use Morefoto\Payment\Application\Payment\Mapper\PaymentOutputMapper;
use Morefoto\Payment\Application\Payment\Service\PaymentReconciler;
use Morefoto\Payment\Domain\Payment\Enum\AttemptStatusEnum;
use Morefoto\Payment\Domain\Payment\Enum\ConfirmationSourceEnum;
use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Service\PaymentAttemptPolicy;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\OrderPaymentInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Отдаёт покупателю серверный результат его попытки оплаты. Возврат со страницы провайдера запускает сверку
 * незавершённой попытки не чаще раза в 5 секунд, поэтому опрос страницы не превращается в поток запросов к провайдеру.
 */
final readonly class GetPaymentAttemptUseCase
{
    private const int CHECK_INTERVAL = 5;

    public function __construct(
        private OrderPaymentInterface $orders,
        private PaymentAttemptRepositoryInterface $attempts,
        private PaymentReconciler $reconciler,
        private PaymentAttemptPolicy $policy,
        private PaymentOutputMapper $mapper,
        private ClockInterface $clock,
    ) {}

    public function execute(?string $orderKey, string $attemptId): PaymentAttemptOutputDto
    {
        $order = $this->orders->byKey($orderKey);
        $attempt = $this->attempts->findPublic($attemptId);
        if (null === $attempt || $attempt['ORDER_ID'] !== $order->id) {
            throw new HttpException('PAYMENT_ATTEMPT_NOT_FOUND', 404);
        }
        $threshold = $this->policy->utc($this->clock->now()->modify('-' . self::CHECK_INTERVAL . ' seconds'));
        if (AttemptStatusEnum::from($attempt['STATUS'])->isOpen() && (null === $attempt['LAST_CHECK_AT'] || $attempt['LAST_CHECK_AT'] <= $threshold)) {
            $this->reconciler->reconcile($attempt['ID'], ConfirmationSourceEnum::RETURN);
            $attempt = $this->attempts->find($attempt['ID']) ?? $attempt;
            $order = $this->orders->byKey($orderKey);
        }

        return $this->mapper->attempt($attempt, $order->version, false);
    }
}
