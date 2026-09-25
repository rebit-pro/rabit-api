<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit\Support;

use Rebit\Share\Contracts\Commerce\Dto\OrderPaymentInputDto;
use Rebit\Share\Contracts\Commerce\Dto\PayableOrderOutputDto;
use Rebit\Share\Contracts\Commerce\OrderPaymentInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Один заказ Commerce с правилами контракта: paid окончателен, смена статуса повышает версию. */
final class FakeOrders implements OrderPaymentInterface
{
    public const string KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    /** @var list<OrderPaymentInputDto> */
    public array $applied = [];
    public int $locks = 0;
    private string $status = 'unpaid';
    private int $version = 1;
    private ?string $paidAt = null;
    private bool $late = false;

    public function __construct(public int $total = 105000, public ?string $closesAt = '2026-10-01 00:00:00') {}

    public function byKey(?string $orderKey): PayableOrderOutputDto
    {
        if (self::KEY !== $orderKey) {
            throw new HttpException('ORDER_NOT_FOUND', 404);
        }

        return $this->order();
    }

    public function lock(int $orderId): PayableOrderOutputDto
    {
        ++$this->locks;

        return $this->order();
    }

    public function applyPayment(OrderPaymentInputDto $input): void
    {
        $this->applied[] = $input;
        if ('paid' === $this->status || $input->status === $this->status) {
            return;
        }
        $this->status = $input->status;
        $this->paidAt = $input->paidAt;
        $this->late = $input->latePayment;
        ++$this->version;
    }

    public function order(): PayableOrderOutputDto
    {
        return new PayableOrderOutputDto(
            id: 7,
            publicId: '77777777-7777-4777-8777-777777777777',
            number: 'MF-0007',
            institutionId: 3,
            institutionName: 'Детский сад',
            groupName: 'Солнышко',
            subtotal: $this->total,
            discount: 0,
            giftSaving: 0,
            total: $this->total,
            paymentStatus: $this->status,
            version: (string)$this->version,
            paidAt: $this->paidAt,
            latePayment: $this->late,
            closesAt: $this->closesAt,
        );
    }
}
