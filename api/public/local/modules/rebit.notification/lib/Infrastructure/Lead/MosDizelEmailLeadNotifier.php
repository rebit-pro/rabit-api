<?php

declare(strict_types=1);

namespace Rebit\Notification\Infrastructure\Lead;

use Psr\Log\LoggerInterface;
use Rebit\Notification\Application\Lead\Dto\LeadAttachmentDto;
use Rebit\Notification\Application\Lead\Dto\LeadMessageDto;
use Rebit\Notification\Application\Lead\Port\MosDizelLeadNotifierInterface;

/**
 * Email-only адаптер заявок mos-dizel.ru.
 */
final readonly class MosDizelEmailLeadNotifier implements MosDizelLeadNotifierInterface
{
    private EmailLeadNotifier $emailNotifier;

    public function __construct(LoggerInterface $logger, string $email, string $siteId, string $eventName)
    {
        $this->emailNotifier = new EmailLeadNotifier($logger, $email, $siteId, $eventName);
    }

    public function notify(LeadMessageDto $lead, ?LeadAttachmentDto $attachment = null): void
    {
        $this->emailNotifier->notify($lead, $attachment);
    }
}
