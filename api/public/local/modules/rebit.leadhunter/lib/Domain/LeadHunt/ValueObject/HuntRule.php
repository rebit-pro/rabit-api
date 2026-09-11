<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Domain\LeadHunt\ValueObject;

use Rebit\Leadhunter\Domain\LeadHunt\Enum\LeadSourceEnum;

/**
 * Правило охоты: какую ленту площадки читать и какие ключевые слова искать.
 *
 * Пустой список keywords означает «брать всё из ленты» — кейс подписки
 * на раздел целиком (раздел задаётся feedParams, например category/subcategory для fl.ru).
 */
final readonly class HuntRule
{
    /**
     * @param array<string, int|string> $feedParams
     * @param list<string>              $keywords
     */
    public function __construct(
        public LeadSourceEnum $source,
        public array $feedParams = [],
        public array $keywords = [],
    ) {}

    public function matchesEverything(): bool
    {
        return [] === $this->keywords;
    }
}
