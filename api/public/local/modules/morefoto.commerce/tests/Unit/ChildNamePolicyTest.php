<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Domain\Order\Service\ChildNamePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class ChildNamePolicyTest extends TestCase
{
    public function testAcceptsAndTrimsEachPartOfTheOrderName(): void
    {
        $name = (new ChildNamePolicy())->accept('  Иван  ', '  Иванов  ');

        self::assertSame(['Иван', 'Иванов'], [$name->firstName, $name->lastName]);
    }

    #[DataProvider('invalidNames')]
    public function testRejectsMissingOrMalformedParts(?string $first, ?string $last, string $code): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($code);
        (new ChildNamePolicy())->accept($first, $last);
    }

    /** @return iterable<string, array{?string, ?string, string}> */
    public static function invalidNames(): iterable
    {
        yield 'missing first' => [null, 'Иванов', 'INVALID_CHILD_FIRST_NAME'];
        yield 'empty last' => ['Иван', '  ', 'INVALID_CHILD_LAST_NAME'];
        yield 'nonbreaking spaces' => ["\u{00A0}\u{00A0}", 'Иванов', 'INVALID_CHILD_FIRST_NAME'];
        yield 'long first' => [str_repeat('И', 101), 'Иванов', 'INVALID_CHILD_FIRST_NAME'];
        yield 'control character' => ['Иван', "Ива\nнов", 'INVALID_CHILD_LAST_NAME'];
    }
}
