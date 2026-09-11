<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Tests\Domain\LeadHunt\Service;

use PHPUnit\Framework\TestCase;
use Rebit\Leadhunter\Domain\LeadHunt\Service\KeywordMatcher;

/**
 * @internal
 */
final class KeywordMatcherTest extends TestCase
{
    private KeywordMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new KeywordMatcher();
    }

    public function testMatchesCyrillicCaseInsensitive(): void
    {
        $matched = $this->matcher->match(
            ['битрикс', 'bitrix'],
            'Доработка сайта на Битрикс: каталог и корзина',
        );

        self::assertSame(['битрикс'], $matched);
    }

    public function testMatchesLatinCaseInsensitive(): void
    {
        $matched = $this->matcher->match(
            ['битрикс', 'bitrix'],
            'Нужен разработчик 1C-BITRIX для интернет-магазина',
        );

        self::assertSame(['bitrix'], $matched);
    }

    public function testReturnsAllMatchedKeywords(): void
    {
        $matched = $this->matcher->match(
            ['битрикс', 'bitrix'],
            'Перенос сайта с Битрикс (bitrix) на другую CMS',
        );

        self::assertSame(['битрикс', 'bitrix'], $matched);
    }

    public function testReturnsEmptyListWhenNothingMatched(): void
    {
        $matched = $this->matcher->match(
            ['битрикс', 'bitrix'],
            'Вёрстка лендинга на Tilda',
        );

        self::assertSame([], $matched);
    }

    public function testSkipsEmptyKeyword(): void
    {
        $matched = $this->matcher->match(
            ['', 'bitrix'],
            'Сайт на bitrix',
        );

        self::assertSame(['bitrix'], $matched);
    }
}
