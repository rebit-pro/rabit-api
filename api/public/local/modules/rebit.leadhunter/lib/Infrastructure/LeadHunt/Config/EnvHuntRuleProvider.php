<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Infrastructure\LeadHunt\Config;

use Psr\Log\LoggerInterface;
use Rebit\Leadhunter\Application\LeadHunt\Port\HuntRuleProviderInterface;
use Rebit\Leadhunter\Domain\LeadHunt\Enum\LeadSourceEnum;
use Rebit\Leadhunter\Domain\LeadHunt\ValueObject\HuntRule;

/**
 * Правила охоты из env REBIT_LEADHUNTER_RULES (JSON).
 *
 * Формат:
 * [
 *   {"source": "flRu", "keywords": ["битрикс", "bitrix"]},
 *   {"source": "flRu", "params": {"category": 2, "subcategory": 27}, "keywords": []}
 * ]
 *
 * Пустые keywords — брать всё из ленты (подписка на раздел целиком).
 * Невалидные записи пропускаются с ошибкой в лог — остальные правила продолжают работать.
 */
final readonly class EnvHuntRuleProvider implements HuntRuleProviderInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private string $rulesJson,
    ) {}

    public function getRules(): array
    {
        if ('' === trim($this->rulesJson)) {
            return [];
        }

        $decoded = json_decode($this->rulesJson, true);

        if (!is_array($decoded)) {
            $this->logger->error('REBIT_LEADHUNTER_RULES: невалидный JSON', [
                'error' => json_last_error_msg(),
            ]);

            return [];
        }

        $rules = [];

        foreach ($decoded as $index => $entry) {
            $rule = $this->buildRule($entry);

            if (null === $rule) {
                $this->logger->error('REBIT_LEADHUNTER_RULES: пропущено невалидное правило', ['index' => $index]);

                continue;
            }

            $rules[] = $rule;
        }

        return $rules;
    }

    private function buildRule(mixed $entry): ?HuntRule
    {
        if (!is_array($entry)) {
            return null;
        }

        $source = LeadSourceEnum::tryFrom((string)($entry['source'] ?? ''));

        if (null === $source) {
            return null;
        }

        $params = $entry['params'] ?? [];
        $keywords = $entry['keywords'] ?? [];

        if (!is_array($params) || !is_array($keywords)) {
            return null;
        }

        $normalizedKeywords = [];

        foreach ($keywords as $keyword) {
            if (is_string($keyword) && '' !== trim($keyword)) {
                $normalizedKeywords[] = trim($keyword);
            }
        }

        /** @var array<string, int|string> $params */
        return new HuntRule(
            source: $source,
            feedParams: $params,
            keywords: $normalizedKeywords,
        );
    }
}
