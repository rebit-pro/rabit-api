<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\UseCase;

use Morefoto\Files\Application\Delivery\Dto\DeliveryReportOutputDto;
use Morefoto\Files\Application\Delivery\Service\OrderEmailSender;
use Morefoto\Files\Application\Files\Contract\DownloadIdGeneratorInterface;
use Morefoto\Files\Domain\Delivery\Enum\DeliveryStatusEnum;
use Morefoto\Files\Domain\Delivery\Repository\DeliveryRepositoryInterface;
use Morefoto\Files\Domain\Delivery\Service\DeliveryPolicy;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\OrderEmailLinkInterface;

/**
 * J2: раз в минуту превращает подтверждённые оплаты в письма со ссылкой на заказ — одно автоматическое письмо на заказ —
 * и ставит ждущие письма в очередь H1. Платёжная транзакция не меняется: источник события — сам факт оплаты, поэтому сбой
 * между оплатой и письмом исправляет следующий прогон.
 */
final readonly class DispatchOrderEmailsUseCase
{
    private const int MAX_PAGES = 10;

    public function __construct(
        private DeliveryRepositoryInterface $deliveries,
        private OrderEmailLinkInterface $links,
        private OrderEmailSender $sender,
        private DownloadIdGeneratorInterface $ids,
        private DeliveryPolicy $policy,
        private ClockInterface $clock,
    ) {}

    public function execute(int $limit): DeliveryReportOutputDto
    {
        $now = $this->clock->now();
        $cursor = $this->deliveries->cursor();
        $created = 0;
        $from = $this->policy->scanFrom($cursor['at']);
        $after = 0;
        $last = null;
        // Keyset pages: the overlap re-reads already handled payments, the unique automatic letter makes that harmless.
        for ($pages = 0; $pages < self::MAX_PAGES; ++$pages) {
            $page = $this->links->paidSince($from, $after, $limit);
            foreach ($page as $order) {
                $created += (int)$this->deliveries->createAuto($this->ids->uuid(), $order->id, $order->email, $now);
                $last = $order;
            }
            if (count($page) < $limit || null === $last) {
                break;
            }
            $from = new \DateTimeImmutable($last->recordedAt, new \DateTimeZone('UTC'));
            $after = $last->id;
        }
        if (null !== $last) {
            $at = new \DateTimeImmutable($last->recordedAt, new \DateTimeZone('UTC'));
            if ($at > $cursor['at'] || ($at == $cursor['at'] && $last->id > $cursor['orderId'])) {
                $this->deliveries->advance($at, $last->id);
            }
        }
        $queued = 0;
        $cancelled = 0;
        foreach ($this->deliveries->held($limit) as $delivery) {
            $status = $this->sender->send($delivery);
            if (DeliveryStatusEnum::QUEUED === $status) {
                ++$queued;
            } elseif (DeliveryStatusEnum::CANCELLED === $status) {
                ++$cancelled;
            }
        }

        return new DeliveryReportOutputDto($created, $queued, $cancelled);
    }
}
