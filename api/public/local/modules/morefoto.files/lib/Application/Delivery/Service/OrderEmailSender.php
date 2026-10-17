<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Delivery\Service;

use Morefoto\Files\Application\Delivery\Contract\OrderEmailComposerInterface;
use Morefoto\Files\Application\Delivery\Dto\OrderLetterInputDto;
use Morefoto\Files\Application\Files\Dto\FileAccessOutputDto;
use Morefoto\Files\Application\Files\Service\OrderFileAccess;
use Morefoto\Files\Domain\Delivery\Enum\DeliveryStatusEnum;
use Morefoto\Files\Domain\Delivery\Repository\DeliveryRepositoryInterface;
use Morefoto\Files\Domain\Delivery\Service\AttachmentPlanner;
use Morefoto\Files\Domain\Delivery\Service\DeliveryPolicy;
use Morefoto\Files\Domain\Delivery\ValueObject\FileDelivery;
use Morefoto\Files\Domain\Delivery\ValueObject\LetterFile;
use Morefoto\Files\Domain\Download\Enum\FilesStateEnum;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\Notification\Dto\EmailAttachmentInputDto;
use Rebit\Share\Application\Contract\Notification\Dto\EmailNotificationInputDto;
use Rebit\Share\Application\Contract\Notification\EmailNotificationInterface;
use Rebit\Share\Contracts\Commerce\OrderEmailLinkInterface;

/**
 * Ставит письма отправки в очередь H1 вне любой транзакции: оригиналы права заказа во вложениях, разложенные по письмам
 * (J5), и ссылка на оплаченный заказ в каждом письме (J2). План писем записывается один раз до постановки, поэтому повтор
 * после сбоя даёт те же письма, и H1 по ключу письма не отправит уже поставленное письмо дважды. Пока отправка выключена
 * (J2-DEC-05), отправка ждёт; заказ без действующего срока файлов писем не получает.
 */
final readonly class OrderEmailSender
{
    public const string CONSUMER = 'files-order-email';

    public function __construct(
        private DeliveryRepositoryInterface $deliveries,
        private OrderEmailLinkInterface $links,
        private OrderFileAccess $access,
        private AttachmentPlanner $planner,
        private OrderEmailComposerInterface $composer,
        private EmailNotificationInterface $notifications,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    /** @return DeliveryStatusEnum состояние отправки после попытки */
    public function send(FileDelivery $delivery): DeliveryStatusEnum
    {
        if (DeliveryStatusEnum::HELD !== $delivery->status || !$this->links->enabled()) {
            return $delivery->status;
        }
        $order = $this->links->find($delivery->orderId);
        $access = null === $order ? null : $this->access->byOrderId($delivery->orderId);
        $state = $access->state ?? FilesStateEnum::UNPAID;
        if (null === $order || null === $access || !in_array($state, [FilesStateEnum::AVAILABLE, FilesStateEnum::EMPTY], true)) {
            $this->deliveries->markCancelled($delivery->publicId, $this->clock->now());
            $this->logger->info('Order email is not needed.', ['deliveryId' => $delivery->publicId, 'state' => $state->value]);

            return DeliveryStatusEnum::CANCELLED;
        }
        $parts = $this->deliveries->parts($delivery->publicId);
        if ([] === $parts) {
            $this->deliveries->plan($delivery->publicId, $this->planner->plan($this->files($access)), $this->clock->now());
            $delivery = $this->deliveries->find($delivery->publicId) ?? $delivery;
            $parts = $this->deliveries->parts($delivery->publicId);
        }
        $link = $this->links->link($delivery->orderId);
        $expiresAt = new \DateTimeImmutable($link->expiresAt, new \DateTimeZone('UTC'));
        $linkOnly = array_map(static fn(LetterFile $file): string => $file->name, $delivery->linkOnly);
        $photos = count($linkOnly);
        foreach ($parts as $part) {
            $photos += count($part->files);
        }
        $first = 1;
        $firstOperation = null;
        foreach ($parts as $part) {
            $operationId = $part->operationId;
            if (null === $operationId) {
                $text = $this->composer->compose($order, $link->key, $expiresAt, new OrderLetterInputDto(
                    number: $part->number,
                    letters: count($parts),
                    attachments: array_map(static fn(LetterFile $file): string => $file->name, $part->files),
                    first: $first,
                    photos: $photos,
                    linkOnly: $linkOnly,
                ));
                $operationId = $this->notifications->queue(new EmailNotificationInputDto(
                    consumer: self::CONSUMER,
                    deduplicationKey: 'order-email:' . $delivery->publicId . ':' . $part->number,
                    recipient: $delivery->recipient,
                    subject: $text->subject,
                    body: $text->body,
                    maxAttempts: DeliveryPolicy::MAX_ATTEMPTS,
                    bodyHtml: $text->html,
                    attachments: array_map(
                        static fn(LetterFile $file): EmailAttachmentInputDto => new EmailAttachmentInputDto($file->name, $file->mimeType, $file->path, $file->bytes),
                        $part->files,
                    ),
                ))->id;
                $this->deliveries->markPartQueued($delivery->publicId, $part->number, $operationId, $this->clock->now());
            }
            $firstOperation ??= $operationId;
            $first += count($part->files);
        }
        if (null === $firstOperation) {
            return DeliveryStatusEnum::HELD;
        }
        $this->deliveries->markQueued($delivery->publicId, $firstOperation, $this->clock->now());
        $this->logger->info('Order email queued.', [
            'deliveryId' => $delivery->publicId,
            'kind' => $delivery->kind->value,
            'letters' => count($parts),
            'operationId' => $firstOperation,
        ]);

        return DeliveryStatusEnum::QUEUED;
    }

    /** @return list<LetterFile> кадры права в порядке страницы заказа */
    private function files(FileAccessOutputDto $access): array
    {
        $files = [];
        foreach ($access->files as $file) {
            $files[] = new LetterFile($file->photoId, $file->filename, $file->mimeType, $file->bytes, $file->absolutePath);
        }

        return $files;
    }
}
