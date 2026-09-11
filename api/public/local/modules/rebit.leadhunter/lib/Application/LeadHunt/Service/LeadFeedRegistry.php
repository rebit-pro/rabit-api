<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Application\LeadHunt\Service;

use Rebit\Leadhunter\Application\LeadHunt\Port\LeadFeedInterface;
use Rebit\Leadhunter\Domain\LeadHunt\Enum\LeadSourceEnum;

/**
 * Реестр лент по площадкам. Собирается в DI: новая площадка — новая запись в карте.
 */
final readonly class LeadFeedRegistry
{
    /**
     * @param array<string, LeadFeedInterface> $feeds ключ — value кейса LeadSourceEnum
     */
    public function __construct(
        private array $feeds,
    ) {}

    public function get(LeadSourceEnum $source): ?LeadFeedInterface
    {
        return $this->feeds[$source->value] ?? null;
    }
}
