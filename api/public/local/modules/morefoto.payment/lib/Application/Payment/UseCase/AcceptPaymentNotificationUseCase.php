<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\UseCase;

use Morefoto\Payment\Application\Payment\Contract\PaymentProviderInterface;
use Morefoto\Payment\Application\Payment\Dto\PaymentNotificationInputDto;
use Morefoto\Payment\Application\Payment\Service\PaymentReconciler;
use Morefoto\Payment\Domain\Payment\Enum\ConfirmationSourceEnum;
use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Repository\PaymentNotificationRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Service\PaymentAttemptPolicy;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Принимает уведомление провайдера о платеже как повод для серверной сверки (G1-DEC-05): тело уведомления
 * не доказывает оплату. Повтор того же события, чужой платёж и неподдерживаемое событие подтверждаются без изменений.
 */
final readonly class AcceptPaymentNotificationUseCase
{
    private const array EVENTS = ['payment.succeeded', 'payment.canceled', 'payment.waiting_for_capture'];

    public function __construct(
        private PaymentProviderInterface $provider,
        private PaymentAttemptRepositoryInterface $attempts,
        private PaymentNotificationRepositoryInterface $notifications,
        private PaymentReconciler $reconciler,
        private PaymentAttemptPolicy $policy,
        private ClockInterface $clock,
    ) {}

    public function execute(PaymentNotificationInputDto $input): void
    {
        if ($input->provider !== $this->provider->code()) {
            throw new HttpException('PROVIDER_NOT_FOUND', 404);
        }
        if (!in_array($input->event, self::EVENTS, true)) {
            return;
        }
        $key = $input->event . ':' . $input->objectId;
        if ($this->notifications->register($input->provider, $key, $input->event, $input->objectId, $this->policy->utc($this->clock->now()))) {
            return;
        }
        $attempt = $this->attempts->findByProviderPayment($input->provider, $input->objectId);
        $status = null === $attempt ? null : $this->reconciler->reconcile($attempt['ID'], ConfirmationSourceEnum::NOTIFICATION);
        $this->notifications->complete($input->provider, $key, $status->value ?? 'unknown_payment', $this->policy->utc($this->clock->now()));
    }
}
