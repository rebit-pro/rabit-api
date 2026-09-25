<?php

declare(strict_types=1);

namespace Rebit\Share\Tests\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Responses\EntityTag;

/**
 * @internal
 */
final class EntityTagTest extends TestCase
{
    #[DataProvider('headers')]
    public function testIfNoneMatch(?string $header, bool $matches): void
    {
        self::assertSame($matches, EntityTag::matches($header, EntityTag::quote('12-3-64')));
    }

    /** @return iterable<string, array{?string, bool}> */
    public static function headers(): iterable
    {
        yield 'no header' => [null, false];
        yield 'same tag' => ['"12-3-64"', true];
        yield 'weak form of the same tag' => ['W/"12-3-64"', true];
        yield 'list with the tag' => ['"12-2-64", "12-3-64"', true];
        yield 'any' => ['*', true];
        yield 'previous version' => ['"12-2-64"', false];
        yield 'other size' => ['"12-3-256"', false];
        yield 'unquoted' => ['12-3-64', false];
    }
}
