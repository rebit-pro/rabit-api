<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order;

use Morefoto\Commerce\Application\Order\Dto\BuyerOrderOutputDto;
use Morefoto\Commerce\Application\Order\Dto\CreatedOrderOutputDto;
use Morefoto\Commerce\Application\Order\Dto\OrderBuyerOutputDto;
use Morefoto\Commerce\Application\Order\Dto\OrderOutputDto;
use Morefoto\Commerce\Application\Order\Dto\OrderPeriodOutputDto;
use Morefoto\Commerce\Application\Order\Dto\StaffOrderDetailOutputDto;
use Morefoto\Commerce\Application\Order\Dto\StaffOrderPageOutputDto;
use Morefoto\Commerce\Presentation\Order\Dto\BuyerOrderResultDto;
use Morefoto\Commerce\Presentation\Order\Dto\CreatedOrderResultDto;
use Morefoto\Commerce\Presentation\Order\Dto\OrderBuyerResultDto;
use Morefoto\Commerce\Presentation\Order\Dto\OrderPeriodResultDto;
use Morefoto\Commerce\Presentation\Order\Dto\StaffOrderDetailResultDto;
use Morefoto\Commerce\Presentation\Order\Dto\StaffOrderListResultDto;
use Morefoto\Commerce\Presentation\Order\Dto\StaffOrderResultDto;

/** Покупательская проекция не содержит ID учреждения/съёмки; служебные — личного ключа и ссылки галереи. */
final readonly class OrderResultMapper
{
    public function created(CreatedOrderOutputDto $output): CreatedOrderResultDto
    {
        $order = $output->order;

        return new CreatedOrderResultDto(
            id: $order->id,
            number: $order->number,
            groupId: $order->groupId,
            accessKey: $output->accessKey,
            accessKeyExpiresAt: $output->accessKeyExpiresAt,
            paymentStatus: $order->paymentStatus,
            productionStatus: $order->productionStatus,
            quote: $order->quote,
            buyer: $this->buyer($order->buyer),
            version: $order->version,
            createdAt: $order->createdAt,
        );
    }

    public function location(): string
    {
        return '/api/v1/public/orders/current';
    }

    public function buyerOrder(BuyerOrderOutputDto $output): BuyerOrderResultDto
    {
        $order = $output->order;

        return new BuyerOrderResultDto(
            id: $order->id,
            number: $order->number,
            groupId: $order->groupId,
            institutionName: $order->institutionName,
            shootName: $order->shootName,
            groupName: $order->groupName,
            audience: $order->audience,
            buyer: $this->buyer($order->buyer),
            quote: $order->quote,
            paymentStatus: $order->paymentStatus,
            paidAt: $order->paidAt,
            latePayment: $order->latePayment,
            productionStatus: $order->productionStatus,
            version: $order->version,
            period: $this->period($output->period),
            accessKeyExpiresAt: $output->accessKeyExpiresAt,
            createdAt: $order->createdAt,
        );
    }

    public function staffList(StaffOrderPageOutputDto $output): StaffOrderListResultDto
    {
        $items = [];
        foreach ($output->items as $order) {
            $items[] = $this->staff($order);
        }

        return new StaffOrderListResultDto($items);
    }

    /**
     * @return array{
     *     page: int,
     *     pageSize: int,
     *     total: int,
     *     totalPages: int,
     *     summary: array{total: int, byProductionStatus: array<string, int>},
     * }
     */
    public function meta(StaffOrderPageOutputDto $output): array
    {
        return [
            'page' => $output->page,
            'pageSize' => $output->pageSize,
            'total' => $output->total,
            'totalPages' => (int)ceil($output->total / $output->pageSize),
            // Payment split waits for the payment provider (G1): until then it would show every order as unpaid.
            'summary' => ['total' => array_sum($output->byProductionStatus), 'byProductionStatus' => $output->byProductionStatus],
        ];
    }

    public function staffDetail(StaffOrderDetailOutputDto $output): StaffOrderDetailResultDto
    {
        $order = $output->order;
        $photos = [];
        foreach ($output->correctionPhotos as $photo) {
            $photos[] = [
                'childId' => $photo->childId, 'childCode' => $photo->childCode, 'assignmentId' => $photo->assignmentId,
                'photoId' => $photo->photoId, 'code' => $photo->code, 'width' => $photo->width, 'height' => $photo->height,
            ];
        }

        return new StaffOrderDetailResultDto(
            id: $order->id,
            number: $order->number,
            institutionId: $order->institutionId,
            institutionName: $order->institutionName,
            shootId: $order->shootId,
            shootName: $order->shootName,
            groupId: $order->groupId,
            groupName: $order->groupName,
            audience: $order->audience,
            createdAt: $order->createdAt,
            buyer: $this->buyer($order->buyer),
            quote: $order->quote,
            paymentStatus: $order->paymentStatus,
            paidAt: $order->paidAt,
            latePayment: $order->latePayment,
            productionStatus: $order->productionStatus,
            version: $order->version,
            period: $this->period($output->period),
            correctionPhotos: $photos,
        );
    }

    private function staff(OrderOutputDto $order): StaffOrderResultDto
    {
        return new StaffOrderResultDto(
            id: $order->id,
            number: $order->number,
            institutionId: $order->institutionId,
            institutionName: $order->institutionName,
            shootId: $order->shootId,
            shootName: $order->shootName,
            groupId: $order->groupId,
            groupName: $order->groupName,
            audience: $order->audience,
            createdAt: $order->createdAt,
            buyer: $this->buyer($order->buyer),
            quote: $order->quote,
            paymentStatus: $order->paymentStatus,
            paidAt: $order->paidAt,
            latePayment: $order->latePayment,
            productionStatus: $order->productionStatus,
            version: $order->version,
        );
    }

    private function buyer(OrderBuyerOutputDto $buyer): OrderBuyerResultDto
    {
        return new OrderBuyerResultDto($buyer->name, $buyer->phone, $buyer->email, $buyer->comment, $buyer->receiptChannel);
    }

    private function period(OrderPeriodOutputDto $period): OrderPeriodResultDto
    {
        return new OrderPeriodResultDto($period->groupId, $period->state, $period->timezone, $period->sentAt, $period->closesAt, $period->deliveryDueAt, $period->now);
    }
}
