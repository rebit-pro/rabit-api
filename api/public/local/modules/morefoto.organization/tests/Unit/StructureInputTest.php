<?php

declare(strict_types=1);

namespace Morefoto\Organization\Tests\Unit;

use Morefoto\Organization\Application\Structure\Dto\GroupMutationInputDto;
use Morefoto\Organization\Application\Structure\Dto\ShootMutationInputDto;
use Morefoto\Organization\Application\Structure\Dto\StructurePageInputDto;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureName;
use Morefoto\Organization\Domain\Structure\ValueObject\ShootDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class StructureInputTest extends TestCase
{
    public function testNullableDateAndLeapDay(): void
    {
        self::assertNull((new ShootDate(null))->value);
        self::assertSame('2028-02-29', (new ShootDate('2028-02-29'))->value);
        self::assertSame('2026-12-31', (new ShootDate('2026-12-31'))->value);
    }

    #[DataProvider('invalidDates')]
    public function testRejectsNonCalendarDates(string $date): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ShootDate($date);
    }

    public static function invalidDates(): iterable
    {
        yield ['2026-02-29'];
        yield ['2026-04-31'];
        yield ['2026-00-10'];
        yield ['2026-10-00'];
        yield ['2026-1-01'];
        yield ['0000-01-01'];
        yield ['2026-10-01T00:00:00+03:00'];
        yield ['2026-10-01 '];
        yield [''];
    }

    public function testOmittedAndNullDateHaveDifferentIdempotencyHashes(): void
    {
        $preserve = new ShootMutationInputDto(str_repeat('a', 32), ' Осень ', false, null, 2);
        $clear = new ShootMutationInputDto(str_repeat('a', 32), 'Осень', true, null, 2);
        self::assertSame('Осень', $preserve->name);
        self::assertNotSame($preserve->payloadHash(), $clear->payloadHash());
        self::assertSame($preserve->payloadHash(), (new ShootMutationInputDto(str_repeat('b', 32), 'Осень', false, null, 2))->payloadHash());
    }

    public function testTeacherOmissionClearAndReasonArePartOfIdempotencyPayload(): void
    {
        $keep = new GroupMutationInputDto(str_repeat('a', 32), 'Группа', null, 1, false, null, 'a2', false);
        $clear = new GroupMutationInputDto(str_repeat('a', 32), 'Группа', null, 1, true, null, 'a2', true, 'Перевод учителя');
        $changed = new GroupMutationInputDto(str_repeat('a', 32), 'Группа', null, 1, true, null, 'a2', true, 'Другая причина');
        self::assertNotSame($keep->payloadHash(), $clear->payloadHash());
        self::assertNotSame($clear->payloadHash(), $changed->payloadHash());
        self::assertSame('Перевод учителя', $clear->reason);
    }

    public function testCanonicalUuidNormalizedNameAndBoundedPage(): void
    {
        self::assertSame('12345678-abcd-4abc-8abc-123456789abc', (new StructureId('12345678-ABCD-4ABC-8ABC-123456789ABC'))->value);
        self::assertSame('Осень', (new StructureName(' Осень '))->value);
        self::assertSame(200, (new StructurePageInputDto(3, 100))->offset());
    }

    #[DataProvider('invalidNames')]
    public function testRejectsInvalidNames(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StructureName($name);
    }

    public static function invalidNames(): iterable
    {
        yield ['  '];
        yield [str_repeat('Я', 256)];
        yield ["bad\0name"];
        yield ["bad\nname"];
        yield ["\xFF"];
    }

    #[DataProvider('invalidGroupMutations')]
    public function testRejectsInvalidMutationContract(?string $kind, ?int $teacher, ?string $signature, ?string $reason): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GroupMutationInputDto(str_repeat('a', 32), 'Группа', $kind, 1, true, $teacher, $signature, false, $reason);
    }

    public static function invalidGroupMutations(): iterable
    {
        yield ['children', null, null, null];
        yield ['regular', 0, null, null];
        yield ['staff', 2147483648, null, null];
        yield ['regular', null, 'a0', null];
        yield ['regular', null, 'a01', null];
        yield ['regular', null, null, '  '];
        yield ['regular', null, null, str_repeat('Я', 2001)];
    }

    #[DataProvider('invalidPages')]
    public function testRejectsUnboundedPages(int $page, int $size): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StructurePageInputDto($page, $size);
    }

    public static function invalidPages(): iterable
    {
        yield [0, 50];
        yield [1, 101];
        yield [1000001, 50];
        yield [1, 0];
    }
}
