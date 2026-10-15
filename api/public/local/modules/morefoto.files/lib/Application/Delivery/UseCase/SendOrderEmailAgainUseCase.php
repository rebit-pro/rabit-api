<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\UseCase;

use Morefoto\Files\Application\Delivery\Dto\DeliveryOutputDto;
use Morefoto\Files\Application\Delivery\Service\DeliveryView;
use Morefoto\Files\Application\Delivery\Service\OrderEmailSender;
use Morefoto\Files\Application\Files\Contract\DownloadIdGeneratorInterface;
use Morefoto\Files\Application\Files\Service\OrderFileAccess;
use Morefoto\Files\Domain\Delivery\Repository\DeliveryRepositoryInterface;
use Morefoto\Files\Domain\Delivery\Service\DeliveryPolicy;
use Morefoto\Files\Domain\Download\Enum\FilesStateEnum;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\OrderEmailLinkInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * FIL-05: покупатель со страницы оплаченного заказа просит письмо со ссылкой ещё раз на email заказа. Не чаще раза в
 * 10 минут и не больше пяти в сутки (J2-DEC-07); ссылка та же, поэтому открытая по прежнему письму страница не закрывается.
 * Повтор запроса с тем же Idempotency-Key возвращает то же письмо.
 */
final readonly class SendOrderEmailAgainUseCase
{
    public function __construct(
        private OrderFileAccess $access,
        private OrderEmailLinkInterface $links,
        private DeliveryRepositoryInterface $deliveries,
        private OrderEmailSender $sender,
        private DeliveryView $view,
        private DownloadIdGeneratorInterface $ids,
        private DeliveryPolicy $policy,
        private ClockInterface $clock,
    ) {}

    public function execute(?string $orderKey, string $idempotencyKey): DeliveryOutputDto
    {
        $access = $this->access->byKey($orderKey);
        $hash = hash('sha256', $idempotencyKey);
        $previous = $this->deliveries->findByIdempotency($access->orderId, $hash);
        if (null !== $previous) {
            return $this->view->view($previous);
        }
        if (!$this->links->enabled()) {
            throw new HttpException('DELIVERY_DISABLED', 409);
        }
        $order = $this->links->find($access->orderId);
        if (null === $order || !in_array($access->state, [FilesStateEnum::AVAILABLE, FilesStateEnum::EMPTY], true)) {
            throw new HttpException('FILES_UNAVAILABLE', 409, null, ['state' => $access->state->value]);
        }
        $now = $this->clock->now();
        $latest = $this->deliveries->latest($access->orderId);
        if (null !== $latest && $now < $this->policy->resendAvailableAt($latest->createdAt)) {
            throw new HttpException('DELIVERY_TOO_SOON', 429, null, [
                'retryAt' => $this->policy->resendAvailableAt($latest->createdAt)->format(\DateTimeInterface::ATOM),
            ]);
        }
        if (DeliveryPolicy::DAILY_LIMIT <= $this->deliveries->countSince($access->orderId, $this->policy->dayStart($now))) {
            throw new HttpException('DELIVERY_LIMIT', 429);
        }
        $id = $this->ids->uuid();
        if (!$this->deliveries->createManual($id, $access->orderId, $order->email, $hash, $now)) {
            $id = $this->deliveries->findByIdempotency($access->orderId, $hash)->publicId ?? throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
        }
        $delivery = $this->deliveries->find($id) ?? throw new HttpException('DELIVERY_NOT_FOUND', 404);
        $this->sender->send($delivery);

        return $this->view->view($this->deliveries->find($id) ?? $delivery);
    }
}
