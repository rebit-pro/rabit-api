<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Application\LeadHunt\Port;

use Rebit\Leadhunter\Application\LeadHunt\Dto\ExternalLeadDto;
use Rebit\Leadhunter\Application\LeadHunt\Dto\PendingLeadDto;
use Rebit\Leadhunter\Domain\LeadHunt\Enum\LeadSourceEnum;

/**
 * Хранилище найденных заявок: дедупликация и журнал доставки.
 */
interface ExternalLeadRepositoryInterface
{
    /**
     * @param list<string> $guids
     *
     * @return list<string> guid'ы, уже сохранённые ранее
     */
    public function findExistingGuids(LeadSourceEnum $source, array $guids): array;

    /**
     * @param list<string> $matchedKeywords
     */
    public function add(ExternalLeadDto $lead, array $matchedKeywords): void;

    /**
     * Заявки к отправке: pending и failed с числом попыток меньше $maxAttempts.
     *
     * @return list<PendingLeadDto>
     */
    public function findPending(int $limit, int $maxAttempts): array;

    public function markSent(int $id): void;

    public function markFailed(int $id, int $attempts): void;

    public function deleteOlderThanDays(int $days): void;
}
