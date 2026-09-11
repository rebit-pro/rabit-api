<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Application\LeadHunt\Dto;

use Rebit\Leadhunter\Domain\LeadHunt\Enum\LeadSourceEnum;

/**
 * Заявка, прочитанная из ленты внешней площадки.
 */
final readonly class ExternalLeadDto
{
    public function __construct(
        public LeadSourceEnum $source,
        public string $guid,
        public string $title,
        public string $description,
        public string $url,
        public ?\DateTimeImmutable $publishedAt,
    ) {}
}
