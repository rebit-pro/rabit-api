<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Application\LeadHunt\Dto;

use Rebit\Leadhunter\Domain\LeadHunt\Enum\LeadSourceEnum;

/**
 * Сохранённая заявка, ожидающая доставки в Telegram.
 */
final readonly class PendingLeadDto
{
    /**
     * @param list<string> $matchedKeywords пустой список — заявка взята по разделу целиком
     */
    public function __construct(
        public int $id,
        public LeadSourceEnum $source,
        public string $title,
        public string $description,
        public string $url,
        public array $matchedKeywords,
        public int $attempts,
    ) {}
}
