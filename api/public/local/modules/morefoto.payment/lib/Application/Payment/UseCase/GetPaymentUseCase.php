<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\UseCase;

use Morefoto\Payment\Application\Payment\Contract\PaymentStaffAccessInterface;
use Morefoto\Payment\Application\Payment\Dto\PaymentCardOutputDto;
use Morefoto\Payment\Application\Payment\Mapper\PaymentOutputMapper;
use Morefoto\Payment\Application\Receipt\Mapper\ReceiptOutputMapper;
use Morefoto\Payment\Domain\Receipt\Enum\ReceiptKindEnum;
use Morefoto\Payment\Domain\Receipt\Repository\PaymentReceiptRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Repository\PaymentFactRepositoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Карточка платежа для сотрудника: попытка, все попытки того же заказа, подтверждённые денежные факты, ход сверки и
 * кассовые чеки заказа (G2).
 * Чужая по области и отсутствующая попытка неотличимы (404).
 */
final readonly class GetPaymentUseCase
{
    public function __construct(
        private PaymentStaffAccessInterface $access,
        private PaymentAttemptRepositoryInterface $attempts,
        private PaymentFactRepositoryInterface $facts,
        private PaymentOutputMapper $mapper,
        private PaymentReceiptRepositoryInterface $receipts,
        private ReceiptOutputMapper $receiptMapper,
    ) {}

    public function execute(int $actorId, string $attemptId): PaymentCardOutputDto
    {
        $scope = $this->access->scope($actorId);
        $attempt = $this->attempts->findPublic($attemptId);
        if (null === $attempt || (null !== $scope->institutionIds && !in_array($attempt['INSTITUTION_ID'], $scope->institutionIds, true))) {
            throw new HttpException('PAYMENT_ATTEMPT_NOT_FOUND', 404);
        }
        $receipts = $this->receipts->forOrder($attempt['ORDER_ID']);
        $statuses = [];
        foreach ($receipts as $receipt) {
            if (ReceiptKindEnum::PAYMENT->value === $receipt['KIND']) {
                $statuses[$receipt['ATTEMPT_ID']] = $receipt['STATUS'];
            }
        }
        $attempts = [];
        $publicIds = [];
        foreach ($this->attempts->forOrder($attempt['ORDER_ID']) as $sibling) {
            $attempts[] = $this->mapper->item($sibling, $statuses[$sibling['ID']] ?? null);
            $publicIds[$sibling['ID']] = $sibling['PUBLIC_ID'];
        }
        $facts = [];
        foreach ($this->facts->forOrder($attempt['ORDER_ID']) as $fact) {
            $facts[] = $this->mapper->fact($fact, $publicIds);
        }
        $this->access->assertUnchanged($actorId, $scope);

        return new PaymentCardOutputDto(
            payment: $this->mapper->item($attempt, $statuses[$attempt['ID']] ?? null),
            providerPaymentId: $attempt['PROVIDER_PAYMENT_ID'],
            checkCount: $attempt['CHECK_COUNT'],
            lastCheckAt: null === $attempt['LAST_CHECK_AT'] ? null : $this->mapper->display($attempt['LAST_CHECK_AT']),
            nextCheckAt: null === $attempt['NEXT_CHECK_AT'] ? null : $this->mapper->display($attempt['NEXT_CHECK_AT']),
            attempts: $attempts,
            facts: $facts,
            receipts: array_map($this->receiptMapper->receipt(...), $receipts),
        );
    }
}
