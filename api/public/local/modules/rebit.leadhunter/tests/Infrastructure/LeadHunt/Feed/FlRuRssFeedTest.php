<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Tests\Infrastructure\LeadHunt\Feed;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rebit\Leadhunter\Domain\LeadHunt\Enum\LeadSourceEnum;
use Rebit\Leadhunter\Infrastructure\LeadHunt\Feed\FlRuRssFeed;

/**
 * @internal
 */
final class FlRuRssFeedTest extends TestCase
{
    private FlRuRssFeed $feed;

    protected function setUp(): void
    {
        $this->feed = new FlRuRssFeed(new NullLogger(), 'https://www.fl.ru/rss/all.xml');
    }

    public function testParsesItemsFromFixture(): void
    {
        $leads = $this->feed->parse($this->loadFixture());

        self::assertCount(3, $leads);

        $first = $leads[0];

        self::assertSame(LeadSourceEnum::FL_RU, $first->source);
        self::assertSame('https://www.fl.ru/projects/1000001/dorabotat-sayt-na-bitriks.html', $first->guid);
        self::assertSame('Доработать сайт на Битрикс (бюджет: 50 000 ₽)', $first->title);
        self::assertSame('Нужно доработать каталог интернет-магазина на 1С-Битрикс.', $first->description);
        self::assertSame('https://www.fl.ru/projects/1000001/dorabotat-sayt-na-bitriks.html', $first->url);
        self::assertNotNull($first->publishedAt);
        self::assertSame('2026-07-13 10:00:00', $first->publishedAt->format('Y-m-d H:i:s'));
    }

    public function testFallsBackToLinkWhenGuidMissing(): void
    {
        $leads = $this->feed->parse($this->loadFixture());

        $withoutGuid = $leads[2];

        self::assertSame('https://www.fl.ru/projects/1000003/zakaz-bez-guid.html', $withoutGuid->guid);
        self::assertSame('', $withoutGuid->description);
        self::assertNull($withoutGuid->publishedAt);
    }

    public function testReturnsEmptyListOnInvalidXml(): void
    {
        self::assertSame([], $this->feed->parse('<html>антибот-заглушка</html>'));
        self::assertSame([], $this->feed->parse(''));
    }

    private function loadFixture(): string
    {
        return (string)file_get_contents(__DIR__ . '/../../../fixtures/flru-rss.xml');
    }
}
