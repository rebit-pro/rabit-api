<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Infrastructure\LeadHunt\Notifier;

use Psr\Log\LoggerInterface;
use Rebit\Leadhunter\Application\LeadHunt\Dto\PendingLeadDto;
use Rebit\Leadhunter\Application\LeadHunt\Port\HuntNotifierInterface;

/**
 * Композит каналов доставки: основной + резервный.
 *
 * Резервный канал включается сразу, как только основной не смог доставить
 * заявку: лиды внешних площадок разбирают за минуты, поэтому ждать
 * исчерпания ретраев дороже, чем прислать письмо вместо сообщения.
 */
final readonly class FallbackHuntNotifier implements HuntNotifierInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private HuntNotifierInterface $primary,
        private HuntNotifierInterface $fallback,
    ) {}

    public function notify(PendingLeadDto $lead): bool
    {
        if ($this->primary->notify($lead)) {
            return true;
        }

        $this->logger->warning('Основной канал доставки недоступен, уходим на резервный', [
            'leadId' => $lead->id,
            'attempts' => $lead->attempts + 1,
        ]);

        return $this->fallback->notify($lead);
    }
}
