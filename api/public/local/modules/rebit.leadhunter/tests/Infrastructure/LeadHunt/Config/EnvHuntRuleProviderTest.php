<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Tests\Infrastructure\LeadHunt\Config;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rebit\Leadhunter\Domain\LeadHunt\Enum\LeadSourceEnum;
use Rebit\Leadhunter\Infrastructure\LeadHunt\Config\EnvHuntRuleProvider;

/**
 * @internal
 */
final class EnvHuntRuleProviderTest extends TestCase
{
    public function testReturnsEmptyListOnEmptyEnv(): void
    {
        self::assertSame([], $this->provider('')->getRules());
    }

    public function testReturnsEmptyListOnInvalidJson(): void
    {
        self::assertSame([], $this->provider('не json')->getRules());
    }

    public function testParsesRules(): void
    {
        $rules = $this->provider(
            '[{"source":"flRu","keywords":[" битрикс ","bitrix"]},'
            . '{"source":"flRu","params":{"category":2,"subcategory":27},"keywords":[]}]',
        )->getRules();

        self::assertCount(2, $rules);

        $byKeywords = $rules[0];

        self::assertSame(LeadSourceEnum::FL_RU, $byKeywords->source);
        self::assertSame([], $byKeywords->feedParams);
        self::assertSame(['битрикс', 'bitrix'], $byKeywords->keywords);
        self::assertFalse($byKeywords->matchesEverything());

        $bySection = $rules[1];

        self::assertSame(['category' => 2, 'subcategory' => 27], $bySection->feedParams);
        self::assertTrue($bySection->matchesEverything());
    }

    public function testSkipsRuleWithUnknownSource(): void
    {
        $rules = $this->provider(
            '[{"source":"unknown","keywords":["x"]},{"source":"flRu","keywords":["bitrix"]}]',
        )->getRules();

        self::assertCount(1, $rules);
        self::assertSame(['bitrix'], $rules[0]->keywords);
    }

    public function testFiltersNonStringAndEmptyKeywords(): void
    {
        $rules = $this->provider('[{"source":"flRu","keywords":["bitrix", 42, "  ", null]}]')->getRules();

        self::assertCount(1, $rules);
        self::assertSame(['bitrix'], $rules[0]->keywords);
    }

    private function provider(string $json): EnvHuntRuleProvider
    {
        return new EnvHuntRuleProvider(new NullLogger(), $json);
    }
}
