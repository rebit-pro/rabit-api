<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Catalog\Dto\ListProductsInputDto;
use Morefoto\Commerce\Application\Catalog\Dto\ProductOutputDto;
use Morefoto\Commerce\Application\Catalog\Dto\UpdateProductInputDto;
use Morefoto\Commerce\Application\Catalog\Mapper\ProductMapper;
use Morefoto\Commerce\Application\Catalog\UseCase\ListProductsUseCase;
use Morefoto\Commerce\Application\Catalog\UseCase\UpdateProductUseCase;
use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogRevisionConflictException;
use Morefoto\Commerce\Domain\Catalog\Exception\InvalidProductException;
use Morefoto\Commerce\Domain\Catalog\Exception\ProductNotFoundException;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductDetails;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductId;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductPatch;
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
        yield 'price bound' => ['price', ProductDetails::MAX_PRICE + 1];
        yield 'negative count' => ['printCount', -1];
        yield 'count storage bound' => ['printCount', 2147483648];
    }

    public function testAcceptsBoundariesWithoutInventingProductRules(): void
    {
        $details = new ProductDetails(str_repeat('Я', 255), str_repeat('Я', 4000), ProductKind::DIGITAL, ProductDetails::MAX_PRICE, 2147483647, str_repeat('Я', 100), str_repeat('Я', 100), true, false);
        self::assertSame(100_000_000, $details->price);
        self::assertSame(2147483647, $details->printCount);
    }

    public function testPatchPreservesFalseZeroAndEmptyStrings(): void
    {
        $current = new ProductOutputDto(self::UUID, 'Photo', 'Description', ProductKind::PHYSICAL, 120, 1, '10x15', 'piece', true, true);
        $patch = new ProductPatch(4, description: '', price: 0, printCount: 0, format: '', unit: '', staffDiscount: false, active: false);
        $result = ProductMapper::apply($patch, $current);
        self::assertSame('Photo', $result->name);
        self::assertSame('', $result->description);
        self::assertSame('', $result->format);
        self::assertSame('', $result->unit);
        self::assertSame(0, $result->price);
        self::assertSame(0, $result->printCount);
        self::assertFalse($result->staffDiscount);
        self::assertFalse($result->active);
    }

    #[DataProvider('digitalQuantityPatches')]
    public function testDigitalQuantityPatchMayChangeOrPreserveTheSavedSetting(
        ProductKind $currentKind,
        bool $currentlyAllowed,
        ?ProductKind $requestedKind,
        ?bool $requestedAllowed,
        bool $expectedAllowed,
    ): void {
        $current = new ProductOutputDto(self::UUID, 'Файл', '', $currentKind, 10000, 0, '', '', false, true, $currentlyAllowed);
        $patch = new ProductPatch(1, kind: $requestedKind, price: 9000, allowDigitalQuantity: $requestedAllowed);

        self::assertSame($expectedAllowed, $patch->allowsDigitalQuantity($currentKind, $currentlyAllowed));
        self::assertSame($expectedAllowed, ProductMapper::apply($patch, $current)->allowDigitalQuantity);
    }

    /** @return iterable<string, array{ProductKind, bool, ?ProductKind, ?bool, bool}> */
    public static function digitalQuantityPatches(): iterable
    {
        yield 'keep allowed' => [ProductKind::DIGITAL, true, null, null, true];
        yield 'keep protected' => [ProductKind::DIGITAL, false, null, null, false];
        yield 'explicit same kind' => [ProductKind::DIGITAL, true, ProductKind::DIGITAL, null, true];
        yield 'explicit false' => [ProductKind::DIGITAL, true, null, false, false];
        yield 'explicit true' => [ProductKind::DIGITAL, false, null, true, true];
        yield 'switch to physical' => [ProductKind::DIGITAL, true, ProductKind::PHYSICAL, null, false];
        yield 'switch to bundle' => [ProductKind::DIGITAL, true, ProductKind::BUNDLE, null, false];
        yield 'return from physical' => [ProductKind::PHYSICAL, false, ProductKind::DIGITAL, null, false];
        yield 'return from bundle' => [ProductKind::BUNDLE, false, ProductKind::DIGITAL, null, false];
        yield 'switch and opt in' => [ProductKind::PHYSICAL, false, ProductKind::DIGITAL, true, true];
    }

    #[DataProvider('nonDigitalKinds')]
    public function testPatchRejectsExplicitDigitalQuantityForAnotherKind(ProductKind $kind): void
    {
        $current = new ProductOutputDto(self::UUID, 'Файл', '', ProductKind::DIGITAL, 10000, 0, '', '', false, true, true);
        $this->expectException(InvalidProductException::class);
        ProductMapper::apply(new ProductPatch(1, kind: $kind, allowDigitalQuantity: true), $current);
    }

    /** @return iterable<string, array{ProductKind}> */
    public static function nonDigitalKinds(): iterable
    {
        yield 'physical' => [ProductKind::PHYSICAL];
        yield 'bundle' => [ProductKind::BUNDLE];
    }

    public function testUpdateResetsDigitalQuantityWhenProductBecomesPhysical(): void
    {
        $repository = $this->createMock(CatalogRepository::class);
        $repository->expects(self::once())->method('lockRevision')->with(true)->willReturn(4);
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn([
            'UF_UUID' => self::UUID, 'UF_NAME' => 'Файл', 'UF_DESCRIPTION' => '', 'UF_KIND' => 'digital',
            'UF_PRICE' => 10000, 'UF_PRINT_COUNT' => 0, 'UF_FORMAT' => '', 'UF_UNIT' => '',
            'UF_STAFF_DISCOUNT' => 0, 'UF_ACTIVE' => 1, 'UF_ALLOW_DIGITAL_QUANTITY' => 1,
        ]);
        $repository->expects(self::once())->method('find')->with(new ProductId(self::UUID))->willReturn($result);
        $repository->expects(self::once())->method('update')->with(
            new ProductId(self::UUID),
            self::callback(static fn(ProductDetails $details): bool => ProductKind::PHYSICAL === $details->kind
                && !$details->allowDigitalQuantity && 10000 === $details->price),
        );
        $repository->expects(self::once())->method('advanceRevision')->with(4)->willReturn(5);

        $output = (new UpdateProductUseCase($repository, $this->transaction()))->execute(
            new UpdateProductInputDto(new ProductId(self::UUID), new ProductPatch(4, kind: ProductKind::PHYSICAL)),
        );

        self::assertSame(5, $output->revision);
    }

    public function testEmptyPatchRejected(): void
    {
        $this->expectException(InvalidProductException::class);
        new ProductPatch(1);
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
        new ProductId("x' OR 1=1");
    }

    public function testStaleUpdateDoesNotReadOrWriteProduct(): void
    {
        $repository = $this->createMock(CatalogRepository::class);
        $repository->expects(self::once())->method('lockRevision')->with(true)->willReturn(4);
        $repository->expects(self::never())->method('find');
        $repository->expects(self::never())->method('update');
        $repository->expects(self::never())->method('advanceRevision');
        $this->expectException(CatalogRevisionConflictException::class);
        (new UpdateProductUseCase($repository, $this->transaction()))->execute(new UpdateProductInputDto(new ProductId(self::UUID), new ProductPatch(3, active: false)));
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
        (new UpdateProductUseCase($repository, $this->transaction()))->execute(new UpdateProductInputDto(new ProductId(self::UUID), new ProductPatch(3, active: false)));
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
