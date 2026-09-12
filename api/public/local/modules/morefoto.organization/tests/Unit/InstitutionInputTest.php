<?php

declare(strict_types=1);

namespace Morefoto\Organization\Tests\Unit;

use Morefoto\Organization\Application\Institution\Dto\ListInstitutionsInputDto;
use Morefoto\Organization\Domain\Institution\Exception\InvalidInstitutionException;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionDetails;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class InstitutionInputTest extends TestCase
{
    public function testNormalizesDetailsAndAllowsAnAddressToBeFilledLater(): void
    {
        $details = new InstitutionDetails('  Детский сад № 7  ', '  ');
        self::assertSame('Детский сад № 7', $details->name);
        self::assertSame('', $details->address);
        self::assertSame(str_repeat('Я', 255), (new InstitutionDetails(str_repeat('Я', 255), str_repeat('Я', 500)))->name);
    }

    #[DataProvider('invalidDetails')]
    public function testRejectsInvalidDetails(string $name, string $address): void
    {
        $this->expectException(InvalidInstitutionException::class);
        new InstitutionDetails($name, $address);
    }

    public static function invalidDetails(): iterable
    {
        yield 'blank name' => ['  ', ''];
        yield 'name too long' => [str_repeat('Я', 256), ''];
        yield 'address too long' => ['Сад', str_repeat('Я', 501)];
        yield 'invalid encoding' => ["\xFF", ''];
        yield 'null byte' => ['Сад', "Адрес\0"];
    }

    public function testCanonicalUuidAndPagination(): void
    {
        self::assertSame('12345678-abcd-4abc-8abc-123456789abc', (new InstitutionId('12345678-ABCD-4ABC-8ABC-123456789ABC'))->value);
        $input = new ListInstitutionsInputDto(' Сад ', 3, 100);
        self::assertSame('Сад', $input->query);
        self::assertSame(200, $input->offset());
        self::assertSame(0, (new ListInstitutionsInputDto())->offset());
    }

    #[DataProvider('invalidIds')]
    public function testRejectsNonCanonicalIdentifiers(string $id): void
    {
        $this->expectException(InvalidInstitutionException::class);
        new InstitutionId($id);
    }

    public static function invalidIds(): iterable
    {
        yield ['1'];
        yield ['not-a-uuid'];
        yield [' 12345678-abcd-4abc-8abc-123456789abc'];
        yield ['12345678abcd4abc8abc123456789abc'];
        yield [str_repeat('x', 36)];
    }

    #[DataProvider('invalidPages')]
    public function testRejectsUnboundedOrInvalidPageInputs(string $query, int $page, int $size): void
    {
        $this->expectException(InvalidInstitutionException::class);
        new ListInstitutionsInputDto($query, $page, $size);
    }

    public static function invalidPages(): iterable
    {
        yield ['', 0, 50];
        yield ['', PHP_INT_MAX, 50];
        yield ['', 1, 0];
        yield ['', 1, 101];
        yield [str_repeat('Я', 101), 1, 50];
        yield ["\xFF", 1, 50];
        yield ["query\0", 1, 50];
    }
}
