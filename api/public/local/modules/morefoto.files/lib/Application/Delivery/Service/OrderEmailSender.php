<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\Service;

use Morefoto\Files\Application\Delivery\Contract\OrderEmailComposerInterface;
use Morefoto\Files\Application\Files\Service\OrderFileAccess;
use Morefoto\Files\Domain\Delivery\Enum\DeliveryStatusEnum;
use Morefoto\Files\Domain\Delivery\Repository\DeliveryRepositoryInterface;
use Morefoto\Files\Domain\Delivery\Service\DeliveryPolicy;
use Morefoto\Files\Domain\Delivery\ValueObject\FileDelivery;
use Morefoto\Files\Domain\Download\Enum\FilesStateEnum;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\Notification\Dto\EmailNotificationInputDto;
use Rebit\Share\Application\Contract\Notification\EmailNotificationInterface;
use Rebit\Share\Contracts\Commerce\OrderEmailLinkInterface;

/**
 * Ставит письмо со ссылкой на оплаченный заказ в очередь H1 вне любой транзакции. Ссылка и текст письма определяются
 * заказом, поэтому повтор после сбоя даёт то же письмо, и H1 по ключу дедупликации не отправит его дважды. Пока отправка
 * выключена (J2-DEC-05), письмо ждёт; заказ без действующего срока файлов письма не получает.
 */
final readonly class OrderEmailSender
{
    public const string CONSUMER = 'files-order-email';

    public function __construct(
        private DeliveryRepositoryInterface $deliveries,
        private OrderEmailLinkInterface $links,
        private OrderFileAccess $access,
        private OrderEmailComposerInterface $composer,
        private EmailNotificationInterface $notifications,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    /** @return DeliveryStatusEnum состояние письма после попытки */
    public function send(FileDelivery $delivery): DeliveryStatusEnum
    {
        if (DeliveryStatusEnum::HELD !== $delivery->status || !$this->links->enabled()) {
            return $delivery->status;
        }
        $order = $this->links->find($delivery->orderId);
        $state = null === $order ? FilesStateEnum::UNPAID : $this->access->byOrderId($delivery->orderId)->state;
        if (null === $order || !in_array($state, [FilesStateEnum::AVAILABLE, FilesStateEnum::EMPTY], true)) {
            $this->deliveries->markCancelled($delivery->publicId, $this->clock->now());
            $this->logger->info('Order email is not needed.', ['deliveryId' => $delivery->publicId, 'state' => $state->value]);

            return DeliveryStatusEnum::CANCELLED;
        }
        $link = $this->links->link($delivery->orderId);
        $text = $this->composer->compose($order, $link->key, new \DateTimeImmutable($link->expiresAt, new \DateTimeZone('UTC')));
        $operation = $this->notifications->queue(new EmailNotificationInputDto(
            consumer: self::CONSUMER,
            deduplicationKey: 'order-email:' . $delivery->publicId,
            recipient: $delivery->recipient,
            subject: $text->subject,
            body: $text->body,
            maxAttempts: DeliveryPolicy::MAX_ATTEMPTS,
            bodyHtml: $text->html,
        ));
        $this->deliveries->markQueued($delivery->publicId, $operation->id, $this->clock->now());
        $this->logger->info('Order email queued.', ['deliveryId' => $delivery->publicId, 'kind' => $delivery->kind->value, 'operationId' => $operation->id]);

        return DeliveryStatusEnum::QUEUED;
    }
}
