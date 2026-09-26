<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Catalog\UseCase\DeleteProductUseCase;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductId;
use Morefoto\Commerce\Presentation\Catalog\Dto\DeleteProductRequestDto;
use Morefoto\Commerce\Presentation\Catalog\ProductRemovalInputMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\CatalogAccessException;
use Rebit\Share\Contracts\Access\CatalogAccessGuardInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * @internal
 */
final class DeleteProductUseCaseTest extends TestCase
{
    private const string UUID = '12345678-abcd-4abc-8abc-123456789abc';

    public function testUnsoldProductLeavesCatalogueAndGroupConditions(): void
    {
        $repository = $this->repository(found: true);
        $repository->method('purchased')->willReturn(false);
        $repository->expects(self::once())->method('delete')->with(new ProductId(self::UUID));
        $repository->expects(self::once())->method('advanceRevision')->with(4);

        $this->useCase($repository)->execute(1, 'token', new ProductId(self::UUID));
    }

    public function testPurchasedProductStays(): void
    {
        $repository = $this->repository(found: true);
        $repository->method('purchased')->willReturn(true);
        $repository->expects(self::never())->method('delete');
        $repository->expects(self::never())->method('advanceRevision');

        $this->expectExceptionObject(new HttpException('PRODUCT_IN_ORDERS', 409));
        $this->useCase($repository)->execute(1, 'token', new ProductId(self::UUID));
    }

    public function testMissingProductIsNotFound(): void
    {
        $repository = $this->repository(found: false);
        $repository->expects(self::never())->method('delete');

        $this->expectExceptionObject(new HttpException('NOT_FOUND', 404));
        $this->useCase($repository)->execute(1, 'token', new ProductId(self::UUID));
    }

    public function testAccessDenialBecomesHttpRefusal(): void
    {
        $repository = $this->createMock(CatalogRepository::class);
        $repository->expects(self::never())->method('lockRevision');
        $access = $this->createStub(CatalogAccessGuardInterface::class);
        $access->method('lockOrganizer')->willThrowException(new CatalogAccessException('denied', 403));

        $this->expectExceptionObject(new HttpException('FORBIDDEN', 403));
        (new DeleteProductUseCase($repository, $access, $this->transaction()))->execute(1, 'token', new ProductId(self::UUID));
    }

    public function testRouteIsMappedToProductAndToken(): void
    {
        $mapper = new ProductRemovalInputMapper();
        $request = new DeleteProductRequestDto(self::UUID, 'Bearer token-value');

        self::assertSame(self::UUID, $mapper->productId($request)->value);
        self::assertSame('token-value', $mapper->token($request));
    }

    private function repository(bool $found): MockObject
    {
        $repository = $this->createMock(CatalogRepository::class);
        $repository->method('lockRevision')->with(true)->willReturn(4);
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn($found ? ['UF_UUID' => self::UUID] : false);
        $repository->method('find')->willReturn($result);

        return $repository;
    }

    private function useCase(MockObject $repository): DeleteProductUseCase
    {
        self::assertInstanceOf(CatalogRepository::class, $repository);

        return new DeleteProductUseCase($repository, $this->createStub(CatalogAccessGuardInterface::class), $this->transaction());
    }

    private function transaction(): CatalogTransactionInterface
    {
        $transaction = $this->createStub(CatalogTransactionInterface::class);
        $transaction->method('execute')->willReturnCallback(static fn(callable $operation): mixed => $operation());

        return $transaction;
    }
}
