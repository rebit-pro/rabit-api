<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\UseCase;

use Morefoto\Payment\Application\Payment\Contract\PaymentStaffAccessInterface;
use Morefoto\Payment\Application\Payment\Contract\PaymentTransactionInterface;
use Morefoto\Payment\Application\Receipt\Dto\HandOverPrintInputDto;
use Morefoto\Payment\Application\Receipt\Dto\PrintHandoversOutputDto;
use Morefoto\Payment\Application\Receipt\Service\PrintHandoverView;
use Morefoto\Payment\Application\Receipt\Service\ReceiptRegistrar;
use Morefoto\Payment\Application\Receipt\Service\ReceiptSettings;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Отмечает фактическую выдачу оплаченной печати группы (PAY-13, G2-DEC-05): по каждому выбранному заказу ставит к
 * отправке один закрывающий чек «Полный расчёт» с зачётом предоплаты. Отметка необратима, уже выданные заказы не
 * меняются, а заказ вне выдачи этой группы отклоняет весь запрос без записи.
 */
final readonly class HandOverPrintUseCase
{
    public function __construct(
        private PaymentStaffAccessInterface $access,
        private PrintHandoverView $view,
        private ReceiptRegistrar $registrar,
        private ReceiptSettings $settings,
        private PaymentTransactionInterface $transaction,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function execute(int $actorId, HandOverPrintInputDto $input): PrintHandoversOutputDto
    {
        if (!$input->confirmed) {
            throw new HttpException('CONFIRMATION_REQUIRED', 422);
        }
        $scope = $this->access->scope($actorId);
        $group = $this->view->group($scope, $input->groupId);
        if (!$this->settings->enabled()) {
            throw new HttpException('RECEIPTS_DISABLED', 409);
        }
        $state = $this->view->build($group);
        $handed = [];
        foreach ($state['view']->orders as $order) {
            $handed[$order->orderId] = PrintHandoverView::HANDED === $order->state;
        }
        $payments = [];
        foreach ($input->orderIds as $orderId) {
            $payment = $state['payments'][$orderId] ?? null;
            if (null === $payment && true !== ($handed[$orderId] ?? null)) {
                throw new HttpException('HANDOVER_CONFLICT', 409);
            }
            if (null !== $payment) {
                $payments[] = $payment;
            }
        }
        $this->access->assertUnchanged($actorId, $scope);
        $now = $this->clock->now();
        $created = $this->transaction->execute(function() use ($payments, $actorId, $now): int {
            $created = 0;
            foreach ($payments as $payment) {
                $created += (int)$this->registrar->printHandedOver($payment, $actorId, $now);
            }

            return $created;
        });
        $this->logger->info('Print handed over.', ['groupId' => $group->id, 'orders' => count($input->orderIds), 'closingReceipts' => $created]);

        return $this->view->build($group)['view'];
    }
}
