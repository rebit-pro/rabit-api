<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Mapper;

use Morefoto\Payment\Application\Payment\Dto\PaymentAttemptOutputDto;
use Morefoto\Payment\Application\Payment\Dto\PaymentFactOutputDto;
use Morefoto\Payment\Application\Payment\Dto\PaymentItemOutputDto;
use Morefoto\Payment\Domain\Payment\Enum\AttemptStatusEnum;
use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Repository\PaymentFactRepositoryInterface;

/**
 * Проекции попытки для покупателя и сотрудника; моменты — бизнес-время Europe/Moscow.
 *
 * @phpstan-import-type AttemptRecord from PaymentAttemptRepositoryInterface
 * @phpstan-import-type FactRecord from PaymentFactRepositoryInterface
 */
final readonly class PaymentOutputMapper
{
    private const string TIMEZONE = 'Europe/Moscow';

    /** @param AttemptRecord $attempt */
    public function attempt(array $attempt, string $orderVersion, bool $created): PaymentAttemptOutputDto
    {
        return new PaymentAttemptOutputDto(
            id: $attempt['PUBLIC_ID'],
            status: $attempt['STATUS'],
            amount: $attempt['AMOUNT'],
            paymentMethod: $attempt['PAYMENT_METHOD'],
            orderVersion: $orderVersion,
            latePayment: $attempt['LATE_PAYMENT'],
            // The provider page is offered only while the payment still waits for the buyer.
            redirectUrl: AttemptStatusEnum::PENDING->value === $attempt['STATUS'] ? $attempt['CONFIRMATION_URL'] : null,
            created: $created,
        );
    }

    /** @param AttemptRecord $attempt */
    public function item(array $attempt): PaymentItemOutputDto
    {
        return new PaymentItemOutputDto(
            id: $attempt['PUBLIC_ID'],
            orderId: $attempt['ORDER_PUBLIC_ID'],
            orderNumber: $attempt['ORDER_NUMBER'],
            institutionName: $attempt['INSTITUTION_NAME'],
            groupName: $attempt['GROUP_NAME'],
            createdAt: $this->display($attempt['CREATED_AT']),
            amount: $attempt['AMOUNT'],
            currency: $attempt['CURRENCY'],
            paymentMethod: $attempt['PAYMENT_METHOD'],
            status: $attempt['STATUS'],
            paidAt: null === $attempt['PAID_AT'] ? null : $this->display($attempt['PAID_AT']),
            latePayment: $attempt['LATE_PAYMENT'],
            incomeAmount: $attempt['INCOME_AMOUNT'],
            cancelReason: $attempt['CANCEL_REASON'],
        );
    }

    /**
     * @param FactRecord         $fact
     * @param array<int, string> $attemptIds публичные ID попыток по внутреннему ID
     */
    public function fact(array $fact, array $attemptIds): PaymentFactOutputDto
    {
        return new PaymentFactOutputDto(
            attemptId: $attemptIds[$fact['ATTEMPT_ID']] ?? '',
            amount: $fact['AMOUNT'],
            incomeAmount: $fact['INCOME_AMOUNT'],
            paidAt: $this->display($fact['PAID_AT']),
            latePayment: $fact['LATE_PAYMENT'],
            confirmedBy: $fact['CONFIRMED_BY'],
        );
    }

    public function display(string $utc): string
    {
        return new \DateTimeImmutable($utc, new \DateTimeZone('UTC'))->setTimezone(new \DateTimeZone(self::TIMEZONE))->format(DATE_ATOM);
    }
}
