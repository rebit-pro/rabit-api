<?php

declare(strict_types=1);

namespace Rebit\Notification\Infrastructure\Lead;

use Psr\Log\LoggerInterface;
use Rebit\Notification\Application\Lead\Dto\LeadAttachmentDto;
use Rebit\Notification\Application\Lead\Dto\LeadMessageDto;
use Rebit\Notification\Application\Lead\Port\LeadNotifierInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Композит каналов доставки заявки: основной + резервный.
 *
 * Заявка с сайта доставляется синхронно, ретраев нет — поэтому резервный
 * канал включается сразу, как только основной не смог доставить. Клиент
 * получает ошибку, только если не сработали оба канала.
 */
final readonly class FallbackLeadNotifier implements LeadNotifierInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private LeadNotifierInterface $primary,
        private LeadNotifierInterface $fallback,
    ) {}

    /**
     * @throws HttpException
     */
    public function notify(LeadMessageDto $lead, ?LeadAttachmentDto $attachment = null): void
    {
        try {
            $this->primary->notify($lead, $attachment);

            return;
        } catch (HttpException $exception) {
            $this->logger->warning('Основной канал доставки заявки недоступен, уходим на резервный', [
                'error' => $exception->getMessage(),
            ]);
        }

        $this->fallback->notify($lead, $attachment);
    }
}
