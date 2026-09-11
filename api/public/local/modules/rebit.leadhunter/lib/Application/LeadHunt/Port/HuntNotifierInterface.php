<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Application\LeadHunt\Port;

use Rebit\Leadhunter\Application\LeadHunt\Dto\PendingLeadDto;

/**
 * Доставка найденной заявки получателю.
 */
interface HuntNotifierInterface
{
    /**
     * @return bool успешно ли доставлено; провал не бросает исключений — ретрай следующим прогоном
     */
    public function notify(PendingLeadDto $lead): bool;
}
