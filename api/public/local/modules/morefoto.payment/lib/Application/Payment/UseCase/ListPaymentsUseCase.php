<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\UseCase;

use Morefoto\Payment\Application\Payment\Contract\PaymentStaffAccessInterface;
use Morefoto\Payment\Application\Payment\Dto\PaymentPageOutputDto;
use Morefoto\Payment\Application\Payment\Dto\SearchPaymentsInputDto;
use Morefoto\Payment\Application\Payment\Mapper\PaymentOutputMapper;
use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Payment\ValueObject\PaymentSearchCriteria;

/** Реестр попыток оплаты для сотрудника в его области заказов: фильтры и область применяются до страницы,
 * права перепроверяются перед выдачей. Ключи заказа, идемпотентности и страница провайдера в реестр не попадают.
 */
final readonly class ListPaymentsUseCase
{
    private const string TIMEZONE = 'Europe/Moscow';

    public function __construct(
        private PaymentStaffAccessInterface $access,
        private PaymentAttemptRepositoryInterface $attempts,
        private PaymentOutputMapper $mapper,
    ) {}

    public function execute(int $actorId, SearchPaymentsInputDto $input): PaymentPageOutputDto
    {
        $scope = $this->access->scope($actorId);
        $criteria = new PaymentSearchCriteria(
            institutionScope: $scope->institutionIds,
            status: $input->status,
            orderNumber: $input->orderNumber,
            createdFrom: null === $input->dateFrom ? null : $this->dayStart($input->dateFrom),
            createdBefore: null === $input->dateTo ? null : $this->dayStart($input->dateTo)->modify('+1 day'),
            latePayment: $input->latePayment,
        );
        $total = $this->attempts->count($criteria);
        $items = [];
        foreach ($this->attempts->page($criteria, $input->pageSize, ($input->page - 1) * $input->pageSize) as $attempt) {
            $items[] = $this->mapper->item($attempt);
        }
        $this->access->assertUnchanged($actorId, $scope);

        return new PaymentPageOutputDto($items, $total, $input->page, $input->pageSize);
    }

    /** Начало календарного дня по Москве в UTC; дата уже проверена на входе. */
    private function dayStart(string $date): \DateTimeImmutable
    {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone(self::TIMEZONE));
        if (false === $day) {
            throw new \InvalidArgumentException('Invalid calendar date.');
        }

        return $day->setTimezone(new \DateTimeZone('UTC'));
    }
}
