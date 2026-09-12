<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Catalog\Service\AuthorizedCatalog;
use Morefoto\Commerce\Application\Catalog\Service\CatalogPayloadHash;
use Morefoto\Commerce\Application\Catalog\UseCase\CreateProductUseCase;
use Morefoto\Commerce\Application\Catalog\UseCase\ListProductsUseCase;
use Morefoto\Commerce\Application\Catalog\UseCase\UpdateProductUseCase;
use Morefoto\Commerce\Domain\Catalog\Exception\IdempotencyConflictException;
use Morefoto\Commerce\Domain\Catalog\Exception\InvalidProductException;
use Morefoto\Commerce\Domain\Catalog\Exception\MalformedCatalogJsonException;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogIdempotencyRepository;
use Morefoto\Commerce\Domain\Catalog\ValueObject\IdempotencyKey;
use Morefoto\Commerce\Presentation\Request\CatalogRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\CatalogAccessException;
use Rebit\Share\Contracts\Access\CatalogAccessGuardInterface;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * @internal
 */
final class CatalogApiTest extends TestCase
{
    private const string ID = '11111111-1111-4111-8111-111111111111';
    private const string KEY = '12345678123456781234567812345678';
    private const array PRODUCT = ['name' => 'Фотография', 'description' => '', 'kind' => 'physical', 'price' => 12500, 'printCount' => 1, 'format' => '10×15', 'unit' => 'шт.', 'staffDiscount' => false, 'active' => true];

    #[DataProvider('invalidProductFields')]
    public function testRejectsWrongTypesAndUnknownFields(string $field, mixed $value): void
    {
        $data = self::PRODUCT;
        $data[$field] = $value;
        $this->expectException(InvalidProductException::class);
        (new CatalogRequestFactory())->create(json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), 'application/json', self::KEY);
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidProductFields(): iterable
    {
        yield 'numeric string' => ['price', '12500'];
        yield 'float' => ['price', 12500.0];
        yield 'boolean string' => ['active', 'true'];
        yield 'boolean integer' => ['staffDiscount', 1];
        yield 'null value' => ['description', null];
        yield 'name integer' => ['name', 123];
        yield 'object' => ['unit', new \stdClass()];
        yield 'array' => ['format', []];
        yield 'unknown kind' => ['kind', 'print'];
        yield 'actor spoof' => ['actorId', 1];
        yield 'unknown extra' => ['unexpected', true];
        yield 'client UUID' => ['id', self::ID];
        yield 'negative money' => ['price', -1];
    }

    #[DataProvider('invalidBodies')]
    public function testInvalidJsonAndObjectShape(string $body, string $exception): void
    {
        $this->expectException($exception);
        (new CatalogRequestFactory())->create($body, 'application/json', self::KEY);
    }

    /** @return iterable<array{string, class-string<\Throwable>}> */
    public static function invalidBodies(): iterable
    {
        yield ['{', MalformedCatalogJsonException::class];
        yield ['', MalformedCatalogJsonException::class];
        yield ['null', InvalidProductException::class];
        yield ['[]', InvalidProductException::class];
        yield ['true', InvalidProductException::class];
        yield ['{}', InvalidProductException::class];
    }

    public function testQueryCannotSupplementMutationBody(): void
    {
        $this->expectException(InvalidProductException::class);
        (new CatalogRequestFactory())->create(json_encode(self::PRODUCT, JSON_THROW_ON_ERROR), 'application/json', self::KEY, ['price' => '1']);
    }

    public function testJsonFieldOrderDoesNotChangeIdempotencyHash(): void
    {
        $factory = new CatalogRequestFactory();
        $hashes = new CatalogPayloadHash();
        $first = $factory->create(json_encode(self::PRODUCT, JSON_THROW_ON_ERROR), 'application/json', self::KEY);
        $second = $factory->create(json_encode(array_reverse(self::PRODUCT, true), JSON_THROW_ON_ERROR), 'application/json; charset=UTF-8', self::KEY);
        self::assertSame($hashes->create($first->input), $hashes->create($second->input));
    }

    public function testPatchExplicitFalseDiffersFromOmission(): void
    {
        $factory = new CatalogRequestFactory();
        $hashes = new CatalogPayloadHash();
        $first = $factory->update('{"revision":1,"price":0}', 'application/json', self::KEY, self::ID);
        $second = $factory->update('{"active":false,"price":0,"revision":1}', 'application/json', self::KEY, self::ID);
        $third = $factory->update('{"revision":1,"price":0,"active":false}', 'application/json', self::KEY, self::ID);
        self::assertNotSame($hashes->update($first->input), $hashes->update($second->input));
        self::assertSame($hashes->update($second->input), $hashes->update($third->input));
    }

    public function testPatchNullIsRejected(): void
    {
        $this->expectException(InvalidProductException::class);
        (new CatalogRequestFactory())->update('{"revision":1,"active":null}', 'application/json', self::KEY, self::ID);
    }

    public function testDefaultPaginationIsFifty(): void
    {
        $input = (new CatalogRequestFactory())->list([])->input;
        self::assertSame(1, $input->page);
        self::assertSame(50, $input->pageSize);
    }

    #[DataProvider('invalidQueries')]
    public function testUnknownAndInvalidQueryRejected(array $query): void
    {
        $this->expectException(InvalidProductException::class);
        (new CatalogRequestFactory())->list($query);
    }

    /** @return iterable<array{array<string, mixed>}> */
    public static function invalidQueries(): iterable
    {
        yield [['unknown' => '1']];
        yield [['revision' => '1']];
        yield [['page' => ['1']]];
        yield [['page' => '0']];
        yield [['pageSize' => '101']];
        yield [['page' => '1.0']];
        yield [['page' => '999999999999999999999999']];
    }

    #[DataProvider('invalidKeys')]
    public function testInvalidIdempotencyKey(string $value): void
    {
        $this->expectException(InvalidProductException::class);
        new IdempotencyKey($value);
    }

    /** @return iterable<array{string}> */
    public static function invalidKeys(): iterable
    {
        yield [''];
        yield [str_repeat('g', 32)];
        yield [str_repeat('a', 31)];
        yield [str_repeat('a', 33)];
        yield [str_repeat('a', 32) . "\n"];
    }

    public function testDeniedReplayNeverReadsSavedResponse(): void
    {
        $access = $this->createMock(CatalogAccessGuardInterface::class);
        $access->expects(self::once())->method('lockOrganizer')->with(11, 'revoked')->willThrowException(new CatalogAccessException('Forbidden', 403));
        $keys = $this->createMock(CatalogIdempotencyRepository::class);
        $keys->expects(self::never())->method('find');
        $this->expectException(CatalogAccessException::class);
        $input = (new CatalogRequestFactory())->create(json_encode(self::PRODUCT, JSON_THROW_ON_ERROR), 'application/json', self::KEY);
        $this->service($access, $keys)->create(11, 'revoked', $input->idempotencyKey, $input->input);
    }

    public function testReplayMismatchDoesNotReachProductWrite(): void
    {
        $access = $this->createMock(CatalogAccessGuardInterface::class);
        $access->expects(self::once())->method('lockOrganizer')->with(11, 'token');
        $keys = $this->createMock(CatalogIdempotencyRepository::class);
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn(['PAYLOAD_HASH' => str_repeat('a', 64), 'PRODUCT_UUID' => self::ID, 'RESULT_REVISION' => '2']);
        $keys->expects(self::once())->method('find')->willReturn($result);
        $keys->expects(self::never())->method('save');
        $this->expectException(IdempotencyConflictException::class);
        $input = (new CatalogRequestFactory())->create(json_encode(self::PRODUCT, JSON_THROW_ON_ERROR), 'application/json', self::KEY);
        $this->service($access, $keys)->create(11, 'token', $input->idempotencyKey, $input->input);
    }

    private function service(CatalogAccessGuardInterface $access, CatalogIdempotencyRepository $keys): AuthorizedCatalog
    {
        $transaction = new class implements CatalogTransactionInterface {
            public function execute(callable $operation): mixed
            {
                return $operation();
            }
        };
        $create = $this->createMock(CreateProductUseCase::class);
        $create->expects(self::never())->method('executeWithinTransaction');

        return new AuthorizedCatalog($transaction, $access, $keys, new CatalogPayloadHash(), $create, $this->createStub(UpdateProductUseCase::class), $this->createStub(ListProductsUseCase::class));
    }
}
