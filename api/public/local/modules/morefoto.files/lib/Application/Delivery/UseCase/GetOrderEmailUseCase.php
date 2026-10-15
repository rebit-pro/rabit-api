<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\UseCase;

use Morefoto\Files\Application\Delivery\Dto\OrderEmailSummaryOutputDto;
use Morefoto\Files\Application\Delivery\Service\DeliveryView;
use Morefoto\Files\Application\Files\Service\OrderFileAccess;
use Morefoto\Files\Domain\Delivery\Repository\DeliveryRepositoryInterface;
use Morefoto\Files\Domain\Delivery\Service\DeliveryPolicy;
use Morefoto\Files\Domain\Download\Enum\FilesStateEnum;
use Rebit\Share\Contracts\Commerce\OrderEmailLinkInterface;

/**
 * Блок письма на странице заказа (J2-DEC-03): куда и когда ушло последнее письмо со ссылкой, его судьба и с какого момента
 * можно попросить письмо ещё раз.
 */
final readonly class GetOrderEmailUseCase
{
    public function __construct(
        private OrderFileAccess $access,
        private OrderEmailLinkInterface $links,
        private DeliveryRepositoryInterface $deliveries,
        private DeliveryView $view,
        private DeliveryPolicy $policy,
    ) {}

    public function execute(?string $orderKey): OrderEmailSummaryOutputDto
    {
        $access = $this->access->byKey($orderKey);
        $enabled = $this->links->enabled();
        $order = $this->links->find($access->orderId);
        $latest = $this->deliveries->latest($access->orderId);
        $paid = null !== $order && in_array($access->state, [FilesStateEnum::AVAILABLE, FilesStateEnum::EMPTY], true);

        return new OrderEmailSummaryOutputDto(
            enabled: $enabled,
            recipient: $order?->email,
            latest: null === $latest ? null : $this->view->view($latest),
            canResend: $enabled && $paid,
            resendAvailableAt: null === $latest ? null : $this->policy->resendAvailableAt($latest->createdAt),
        );
    }
}
