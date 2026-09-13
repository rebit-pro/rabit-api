<?php

declare(strict_types=1);

namespace Morefoto\Organization\Tests\Unit;

use Morefoto\Organization\Application\Institution\Dto\InstitutionDetailInputDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class InstitutionDetailInputTest extends TestCase
{
    public function testIndependentPagesShareOnlyPageSize(): void
    {
        $input = new InstitutionDetailInputDto(3, 7, 25);
        self::assertSame(50, $input->shoots->offset());
        self::assertSame(150, $input->groups->offset());
        self::assertSame(25, $input->shoots->pageSize);
        self::assertSame(25, $input->groups->pageSize);
        self::assertSame(50, (new InstitutionDetailInputDto())->shoots->pageSize);
    }

    #[DataProvider('invalidBounds')]
    public function testRejectsInvalidBoundsOnEitherPage(int $shoots, int $groups, int $size): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new InstitutionDetailInputDto($shoots, $groups, $size);
    }

    /** @return iterable<array{int,int,int}> */
    public static function invalidBounds(): iterable
    {
        yield [0, 1, 50];
        yield [1, 0, 50];
        yield [-1, 1, 50];
        yield [1, -1, 50];
        yield [1000001, 1, 50];
        yield [1, 1000001, 50];
        yield [1, 1, 0];
        yield [1, 1, 101];
    }
}
