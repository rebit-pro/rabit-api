<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Catalog\Dto\ListProductsInputDto;
use Morefoto\Commerce\Application\Catalog\Dto\ProductOutputDto;
use Morefoto\Commerce\Application\Catalog\Dto\UpdateProductInputDto;
use Morefoto\Commerce\Application\Catalog\UseCase\ListProductsUseCase;
use Morefoto\Commerce\Application\Catalog\UseCase\UpdateProductUseCase;
use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogRevisionConflictException;
use Morefoto\Commerce\Domain\Catalog\Exception\InvalidProductException;
use Morefoto\Commerce\Domain\Catalog\Exception\ProductNotFoundException;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductDetails;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductId;
use Morefoto\Commerce\Infrastructure\Adapter\ProductIdGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * @internal
 */
final class CatalogTest extends TestCase
{
    private const string UUID = '11111111-1111-4111-8111-111111111111';

    #[DataProvider('invalidDetails')]
    public function testRejectsInvalidProductDetails(string $field, mixed $value): void
    {
        $arguments = ['name' => 'Фото', 'description' => '', 'kind' => ProductKind::PHYSICAL, 'price' => 0, 'printCount' => 0, 'format' => '', 'unit' => '', 'staffDiscount' => false, 'active' => false];
        $arguments[$field] = $value;
        $this->expectException(InvalidProductException::class);
        new ProductDetails(...$arguments);
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidDetails(): iterable
    {
        yield 'blank' => ['name', '   '];
        yield 'name length' => ['name', str_repeat('Я', 256)];
        yield 'description length' => ['description', str_repeat('Я', 4001)];
        yield 'format length' => ['format', str_repeat('a', 101)];
        yield 'unit length' => ['unit', str_repeat('a', 101)];
        yield 'invalid UTF8' => ['name', "\xff"];
        yield 'NUL' => ['description', "foo\0bar"];
        yield 'negative price' => ['price', -1];
        yield 'price storage bound' => ['price', 2147483648];
        yield 'negative count' => ['printCount', -1];
        yield 'count storage bound' => ['printCount', 2147483648];
    }

    public function testAcceptsBoundariesWithoutInventingProductRules(): void
    {
        $details = new ProductDetails(str_repeat('Я', 255), str_repeat('Я', 4000), ProductKind::DIGITAL, 2147483647, 2147483647, str_repeat('Я', 100), str_repeat('Я', 100), true, false);
        self::assertSame(2147483647, $details->price);
        self::assertSame(2147483647, $details->printCount);
    }

    public function testPatchPreservesFalseZeroAndEmptyStrings(): void
    {
        $current = new ProductOutputDto(self::UUID, 'Photo', 'Description', ProductKind::PHYSICAL, 120, 1, '10x15', 'piece', true, true);
        $patch = new UpdateProductInputDto(self::UUID, 4, description: '', price: 0, printCount: 0, format: '', unit: '', staffDiscount: false, active: false);
        $result = $patch->apply($current);
        self::assertSame('Photo', $result->name);
        self::assertSame('', $result->description);
        self::assertSame('', $result->format);
        self::assertSame('', $result->unit);
        self::assertSame(0, $result->price);
        self::assertSame(0, $result->printCount);
        self::assertFalse($result->staffDiscount);
        self::assertFalse($result->active);
    }

    public function testEmptyPatchRejected(): void
    {
        $this->expectException(InvalidProductException::class);
        new UpdateProductInputDto(self::UUID, 1);
    }

    #[DataProvider('invalidPagination')]
    public function testInvalidPagination(int $page, int $size, ?int $revision): void
    {
        $this->expectException(InvalidProductException::class);
        new ListProductsInputDto($page, $size, $revision);
    }

    /** @return iterable<array{int, int, ?int}> */
    public static function invalidPagination(): iterable
    {
        yield [0, 20, null];
        yield [1000001, 20, null];
        yield [1, 0, null];
        yield [1, 101, null];
        yield [1, 20, 0];
    }

    public function testGeneratedIdsAreCanonicalAndDifferent(): void
    {
        $generator = new ProductIdGenerator();
        $first = $generator->generate();
        self::assertSame($first->value, (new ProductId($first->value))->value);
        self::assertNotSame($first->value, $generator->generate()->value);
    }

    public function testMalformedIdRejectedBeforePersistence(): void
    {
        $this->expectException(InvalidProductException::class);
        new UpdateProductInputDto("x' OR 1=1", 1, name: 'Photo');
    }

    public function testStaleUpdateDoesNotReadOrWriteProduct(): void
    {
        $repository = $this->createMock(CatalogRepository::class);
        $repository->expects(self::once())->method('lockRevision')->with(true)->willReturn(4);
        $repository->expects(self::never())->method('find');
        $repository->expects(self::never())->method('update');
        $repository->expects(self::never())->method('advanceRevision');
        $this->expectException(CatalogRevisionConflictException::class);
        (new UpdateProductUseCase($repository, $this->transaction()))->execute(new UpdateProductInputDto(self::UUID, 3, active: false));
    }

    public function testMissingProductDoesNotAdvanceRevision(): void
    {
        $repository = $this->createMock(CatalogRepository::class);
        $repository->method('lockRevision')->willReturn(3);
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn(false);
        $repository->method('find')->willReturn($result);
        $repository->expects(self::never())->method('update');
        $repository->expects(self::never())->method('advanceRevision');
        $this->expectException(ProductNotFoundException::class);
        (new UpdateProductUseCase($repository, $this->transaction()))->execute(new UpdateProductInputDto(self::UUID, 3, active: false));
    }

    public function testStalePaginationStopsBeforeReadingItems(): void
    {
        $repository = $this->createMock(CatalogRepository::class);
        $repository->expects(self::once())->method('lockRevision')->with(false)->willReturn(4);
        $repository->expects(self::never())->method('list');
        $repository->expects(self::never())->method('count');
        $this->expectException(CatalogRevisionConflictException::class);
        (new ListProductsUseCase($repository, $this->transaction()))->execute(new ListProductsInputDto(2, 20, 3));
    }

    private function transaction(): CatalogTransactionInterface
    {
        return new class implements CatalogTransactionInterface {
            public function execute(callable $operation): mixed
            {
                return $operation();
            }
        };
    }
}
