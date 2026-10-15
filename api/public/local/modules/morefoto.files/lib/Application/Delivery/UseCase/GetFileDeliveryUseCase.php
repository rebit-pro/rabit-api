<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\UseCase;

use Morefoto\Files\Application\Delivery\Dto\DeliveryOutputDto;
use Morefoto\Files\Application\Delivery\Service\DeliveryView;
use Morefoto\Files\Application\Files\Service\OrderFileAccess;
use Morefoto\Files\Domain\Delivery\Repository\DeliveryRepositoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** FIL-06: судьба одного письма заказа по ключу страницы; письмо чужого заказа отвечает как несуществующее. */
final readonly class GetFileDeliveryUseCase
{
    public function __construct(
        private OrderFileAccess $access,
        private DeliveryRepositoryInterface $deliveries,
        private DeliveryView $view,
    ) {}

    public function execute(?string $orderKey, string $deliveryId): DeliveryOutputDto
    {
        $access = $this->access->byKey($orderKey);
        $delivery = $this->deliveries->find($deliveryId);
        if (null === $delivery || $delivery->orderId !== $access->orderId) {
            throw new HttpException('DELIVERY_NOT_FOUND', 404);
        }

        return $this->view->view($delivery);
    }
}
