<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\UseCase;

use Morefoto\Payment\Application\Receipt\Service\ReceiptReconciler;
use Morefoto\Payment\Domain\Receipt\Repository\PaymentReceiptRepositoryInterface;
use Morefoto\Payment\Application\Payment\Dto\ReconcileReportOutputDto;
use Morefoto\Payment\Application\Payment\Service\PaymentReconciler;
use Morefoto\Payment\Domain\Payment\Enum\ConfirmationSourceEnum;
use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Service\PaymentAttemptPolicy;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;

/** Фоновая сверка открытых попыток, у которых наступил срок проверки: доводит до исхода платежи, о которых не пришло
 * уведомление и с которых покупатель не вернулся, и повторяет создание после сбоя. Затем сверяет кассовые чеки (G2): о них
 * провайдер уведомлений не шлёт. Сбой одной попытки или чека не останавливает остальные.
 */
final readonly class ReconcilePaymentsUseCase
{
    public function __construct(
        private PaymentAttemptRepositoryInterface $attempts,
        private PaymentReconciler $reconciler,
        private PaymentAttemptPolicy $policy,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private PaymentReceiptRepositoryInterface $receipts,
        private ReceiptReconciler $receiptReconciler,
    ) {}

    public function execute(int $limit): ReconcileReportOutputDto
    {
        $checked = 0;
        $failed = 0;
        foreach ($this->attempts->due($this->policy->utc($this->clock->now()), $limit) as $attemptId) {
            try {
                $this->reconciler->reconcile($attemptId, ConfirmationSourceEnum::RECONCILE);
                ++$checked;
            } catch (\Throwable $error) {
                ++$failed;
                $this->logger->error('Payment reconciliation failed.', ['attemptInternalId' => $attemptId, 'error' => $error::class]);
            }
        }

        $receiptsChecked = 0;
        $receiptsFailed = 0;
        // Receipts run after attempts: a payment confirmed in this run puts its receipt on the schedule for the next one.
        foreach ($this->receipts->due($this->policy->utc($this->clock->now()), $limit) as $receiptId) {
            try {
                $this->receiptReconciler->reconcile($receiptId);
                ++$receiptsChecked;
            } catch (\Throwable $error) {
                ++$receiptsFailed;
                $this->logger->error('Receipt reconciliation failed.', ['receiptInternalId' => $receiptId, 'error' => $error::class]);
            }
        }

        return new ReconcileReportOutputDto($checked, $failed, $receiptsChecked, $receiptsFailed);
    }
}
