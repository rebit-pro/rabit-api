<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Domain\LeadHunt\Service;

/**
 * Регистронезависимый поиск ключевых слов в тексте заявки (включая кириллицу).
 */
final readonly class KeywordMatcher
{
    /**
     * @param list<string> $keywords
     *
     * @return list<string> сработавшие ключевые слова в исходном виде
     */
    public function match(array $keywords, string $text): array
    {
        $matched = [];

        foreach ($keywords as $keyword) {
            if ('' !== $keyword && false !== mb_stripos($text, $keyword)) {
                $matched[] = $keyword;
            }
        }

        return $matched;
    }
}
