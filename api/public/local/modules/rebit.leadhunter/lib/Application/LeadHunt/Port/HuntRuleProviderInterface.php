<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Application\LeadHunt\Port;

use Rebit\Leadhunter\Domain\LeadHunt\ValueObject\HuntRule;

/**
 * Источник правил охоты (env, в перспективе — таблица с админкой).
 */
interface HuntRuleProviderInterface
{
    /**
     * @return list<HuntRule>
     */
    public function getRules(): array;
}
