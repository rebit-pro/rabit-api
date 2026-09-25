<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Payment;

use Morefoto\Payment\Application\Payment\Dto\PaymentNotificationInputDto;
use Morefoto\Payment\Application\Payment\Dto\SearchPaymentsInputDto;
use Morefoto\Payment\Application\Payment\Dto\StartPaymentInputDto;
use Morefoto\Payment\Domain\Payment\Enum\AttemptStatusEnum;
use Morefoto\Payment\Domain\Payment\ValueObject\IdempotencyKey;
use Morefoto\Payment\Presentation\Payment\Request\Dto\PaymentListRequestDto;
use Morefoto\Payment\Presentation\Payment\Request\Dto\PaymentNotificationRequestDto;
use Morefoto\Payment\Presentation\Payment\Request\Dto\StartPaymentRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class PaymentInputMapper
{
    private const string UUID = '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D';

    public function key(StartPaymentRequestDto $request): IdempotencyKey
    {
        return new IdempotencyKey($request->idempotencyKey);
    }

    public function start(StartPaymentRequestDto $request): StartPaymentInputDto
    {
        if (null !== $request->precedingAttemptId && 1 !== preg_match(self::UUID, $request->precedingAttemptId)) {
            throw new HttpException('ATTEMPT_CONFLICT', 409);
        }
        if (1 !== preg_match('/^\d{1,20}$/D', $request->orderVersion) || 1 !== preg_match('/^[a-f0-9]{64}$/D', $request->quoteToken)) {
            throw new HttpException('QUOTE_CHANGED', 409);
        }

        return new StartPaymentInputDto($request->orderVersion, $request->precedingAttemptId, $request->quoteToken, $request->paymentMethod);
    }

    public function notification(PaymentNotificationRequestDto $request): PaymentNotificationInputDto
    {
        $objectId = $request->object['id'] ?? null;
        if (!is_string($objectId) || 1 !== preg_match('/^[A-Za-z0-9-]{1,64}$/D', $objectId) || 1 !== preg_match('/^[a-z_.]{1,64}$/D', $request->event)) {
            throw new HttpException('INVALID_NOTIFICATION', 422);
        }

        return new PaymentNotificationInputDto($request->provider, $request->event, $objectId);
    }

    public function search(PaymentListRequestDto $request): SearchPaymentsInputDto
    {
        if (1 > $request->page || 1000000 < $request->page || 1 > $request->pageSize || 100 < $request->pageSize) {
            throw new HttpException('INVALID_PAGE', 422);
        }
        $status = $this->optional($request->status);
        $late = $this->optional($request->late);
        $number = $this->optional($request->orderNumber);
        if ((null !== $status && null === AttemptStatusEnum::tryFrom($status))
            || (null !== $late && !in_array($late, ['true', 'false'], true))
            || (null !== $number && 1 !== preg_match('/^[A-Za-z0-9-]{1,20}$/D', $number))) {
            throw new HttpException('INVALID_FILTER', 422);
        }
        $dateFrom = $this->date($request->dateFrom);
        $dateTo = $this->date($request->dateTo);
        if (null !== $dateFrom && null !== $dateTo && $dateFrom > $dateTo) {
            throw new HttpException('INVALID_FILTER', 422);
        }

        return new SearchPaymentsInputDto(
            status: $status,
            orderNumber: $number,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            latePayment: null === $late ? null : 'true' === $late,
            page: $request->page,
            pageSize: $request->pageSize,
        );
    }

    private function optional(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }

    private function date(?string $value): ?string
    {
        $value = $this->optional($value);
        if (null === $value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new HttpException('INVALID_FILTER', 422);
        }

        return $value;
    }
}
