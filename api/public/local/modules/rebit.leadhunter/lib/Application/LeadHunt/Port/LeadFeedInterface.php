<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Application\LeadHunt\Port;

use Rebit\Leadhunter\Application\LeadHunt\Dto\ExternalLeadDto;
use Rebit\Leadhunter\Domain\LeadHunt\ValueObject\HuntRule;

/**
 * Лента заявок одной внешней площадки.
 *
 * Реализация не бросает исключений: при недоступности площадки логирует
 * ошибку и возвращает пустой список — следующий прогон догонит.
 */
interface LeadFeedInterface
{
    /**
     * @return list<ExternalLeadDto>
     */
    public function fetch(HuntRule $rule): array;
}
