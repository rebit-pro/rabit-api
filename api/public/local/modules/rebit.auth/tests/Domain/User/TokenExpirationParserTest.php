<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Domain\User;

use Bitrix\Main\Type\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Auth\Domain\User\Service\TokenExpirationParser;

/**
 * @internal
 */
final class TokenExpirationParserTest extends TestCase
{
    #[DataProvider('invalidValues')]
    public function testInvalidExpiryFailsClosed(mixed $value): void
    {
        self::assertNull(TokenExpirationParser::parse($value));
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidValues(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'bool' => [false];
        yield 'int' => [1800000000];
        yield 'garbage' => ['tomorrow'];
        yield 'null byte' => ["2026-09-11 10:00:00\0"];
        yield 'overflow day' => ['2026-02-30 10:00:00'];
        yield 'overflow hour' => ['2026-09-11 25:00:00'];
        yield 'missing seconds' => ['2026-09-11 10:00'];
        yield 'suffix' => ['11.09.2026 10:00:00 garbage'];
        yield 'leading space' => [' 11.09.2026 10:00:00'];
        yield 'trailing space' => ['11.09.2026 10:00:00 '];
        yield 'array' => [[]];
    }

    public function testCanonicalRoundtripDoesNotDependOnServerTimezone(): void
    {
        $timezone = date_default_timezone_get();
        try {
            $timestamp = 1800000000;
            date_default_timezone_set('Europe/Moscow');
            $stored = TokenExpirationParser::format(DateTime::createFromTimestamp($timestamp));
            date_default_timezone_set('America/New_York');
            self::assertSame($timestamp, TokenExpirationParser::parse($stored)?->getTimestamp());
            self::assertStringEndsWith('Z', $stored);
        } finally {
            date_default_timezone_set($timezone);
        }
    }

    public function testBothLegacyFormatsUseOriginalServerTimezone(): void
    {
        $expected = (new \DateTimeImmutable('2026-09-11 10:20:30'))->getTimestamp();
        self::assertSame($expected, TokenExpirationParser::parse('2026-09-11 10:20:30')?->getTimestamp());
        self::assertSame($expected, TokenExpirationParser::parse('11.09.2026 10:20:30')?->getTimestamp());
        $native = DateTime::createFromTimestamp($expected);
        self::assertSame($native, TokenExpirationParser::parse($native));
    }
}
