<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Catalog\Contract\ProductIdGeneratorInterface;
use Morefoto\Commerce\Application\Catalog\Dto\ListProductsInputDto;
use Morefoto\Commerce\Application\Catalog\Dto\ProductInputDto;
use Morefoto\Commerce\Application\Catalog\Dto\UpdateProductInputDto;
use Morefoto\Commerce\Application\Catalog\UseCase\CreateProductUseCase;
use Morefoto\Commerce\Application\Catalog\UseCase\ListProductsUseCase;
use Morefoto\Commerce\Application\Catalog\UseCase\UpdateProductUseCase;
use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogRevisionConflictException;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogStorageException;
use Morefoto\Commerce\Domain\Catalog\Exception\InvalidProductException;
use Morefoto\Commerce\Domain\Catalog\Exception\ProductNotFoundException;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductId;
use Sprint\Migration\Version20260911220001;

$checks = [];
$stage = 'bootstrap';
$assert = static function(bool $condition, string $name) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('Check failed: ' . $name);
    }
    $checks[] = $name;
};
$expect = static function(callable $operation, string $class, string $name) use ($assert): void {
    try {
        $operation();
    } catch (Throwable $exception) {
        $assert($exception instanceof $class, $name . ' (' . $exception::class . ')');

        return;
    }
    throw new RuntimeException('Expected rejection: ' . $name);
};
$quiet = static function(callable $operation): void {
    ob_start();
    try {
        $operation();
    } finally {
        ob_end_clean();
    }
};

try {
    $fixture = require __DIR__ . '/fixtures/w02/bootstrap.php';
    $sql = $fixture['connection'];
    require_once '/kernel/modules/highloadblock/install/index.php';
    $assert((new highloadblock())->InstallDB(), 'native highloadblock installer');
    symlink('/app/public/local/modules/morefoto.commerce', $fixture['documentRoot'] . '/local/modules/morefoto.commerce');
    require '/app/public/local/modules/morefoto.commerce/install/index.php';
    $module = new Morefoto_Commerce();
    $module->DoInstall();
    $module->DoInstall();
    $assert(Loader::includeModule('morefoto.commerce'), 'native module registration and include');
    $assert(class_exists(CreateProductUseCase::class), 'native Loader resolves module namespace without composer changes');

    $stage = 'migration';
    require '/app/public/local/php_interface/migrations.foundation/Version20260911220001.php';
    $migration = new Version20260911220001();
    $quiet(static function() use ($migration): void {
        $migration->up();
        $migration->up();
    });
    $assert(1 === (int)$sql->query("SELECT COUNT(*) AS C FROM b_hlblock_entity WHERE NAME='MfProduct'")->fetch_assoc()['C'], 'one native Product HL after repeated migration');
    $assert(12 === (int)$sql->query("SELECT COUNT(*) AS C FROM b_user_field WHERE ENTITY_ID = CONCAT('HLBLOCK_', (SELECT ID FROM b_hlblock_entity WHERE NAME='MfProduct'))")->fetch_assoc()['C'], 'all 12 native user fields present');
    $assert(1 === (int)$sql->query('SELECT REVISION FROM mf_catalog_state WHERE ID=1')->fetch_assoc()['REVISION'], 'initial catalogue revision one');
    $assert('InnoDB' === $sql->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='b_hlbd_mf_product'")->fetch_assoc()['ENGINE'], 'Product storage is InnoDB');
    $assert('ascii_bin' === $sql->query("SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='b_hlbd_mf_product' AND COLUMN_NAME='UF_UUID'")->fetch_assoc()['COLLATION_NAME'], 'UUID lookup uses binary collation');
    $assert(1 === (int)$sql->query("SELECT COUNT(*) AS C FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='b_hlbd_mf_product' AND INDEX_NAME='ux_mf_product_uuid' AND NON_UNIQUE=0")->fetch_assoc()['C'], 'unique public UUID index exists');
    $quiet(static function() use ($migration): void {
        $migration->down();
        $migration->down();
        $migration->up();
    });
    $assert(0 === (int)$sql->query('SELECT COUNT(*) AS C FROM b_hlbd_mf_product')->fetch_assoc()['C'], 'empty rollback and reinstall contain no seeded products');

    $stage = 'native-di';
    $services = ServiceLocator::getInstance();
    $create = $services->get(CreateProductUseCase::class);
    $update = $services->get(UpdateProductUseCase::class);
    $list = $services->get(ListProductsUseCase::class);
    $repository = $services->get(CatalogRepository::class);
    $transaction = $services->get(CatalogTransactionInterface::class);
    $assert($create instanceof CreateProductUseCase && $update instanceof UpdateProductUseCase && $list instanceof ListProductsUseCase, 'native module DI resolves all catalogue scenarios');
    $assert($create === $services->get(CreateProductUseCase::class), 'scenario service is singleton without request state');
    $empty = $list->execute(new ListProductsInputDto());
    $assert([] === $empty->items && 0 === $empty->total && 1 === $empty->revision, 'empty catalogue produces real zero and revision');
    $product = new ProductInputDto(name: "Фото 'семья' 🖼", description: "Строка 1\nСтрока 2", kind: ProductKind::PHYSICAL, price: 12345, printCount: 2, format: '10×15', unit: 'шт.', staffDiscount: true, active: true);
    $first = $create->execute($product);
    $assert(2 === $first->revision && $first->id === (new ProductId($first->id))->value, 'create returns UUID and advances global revision');
    $second = $create->execute(new ProductInputDto('Digital', '', ProductKind::DIGITAL, 0, 0, '', '', false, false));
    $third = $create->execute(new ProductInputDto('Bundle', '', ProductKind::BUNDLE, 2147483647, 2147483647, '', '', false, true));
    $assert(3 === $second->revision && 4 === $third->revision, 'all kinds use integer prices and one global revision');
    $page = $list->execute(new ListProductsInputDto(1, 2));
    $assert(4 === $page->revision && 3 === $page->total && 2 === count($page->items), 'page items total and revision are consistent');
    $assert($first->id === $page->items[0]->id && $second->id === $page->items[1]->id, 'stable insertion order independent of product names and UUID ordering');
    $assert($product->details->name === $page->items[0]->name && $product->details->description === $page->items[0]->description, 'SQL escaping and utf8mb4 round trip');
    $assert(12345 === $page->items[0]->price && 2 === $page->items[0]->printCount && $page->items[0]->staffDiscount, 'typed product row mapping');
    $assert(!$page->items[1]->active && 0 === $page->items[1]->price, 'inactive products remain in internal catalogue');
    $last = $list->execute(new ListProductsInputDto(2, 2, 4));
    $assert(1 === count($last->items) && $third->id === $last->items[0]->id, 'revision-bound next page has no overlap');
    $assert(2147483647 === $last->items[0]->price && 2147483647 === $last->items[0]->printCount, 'integer storage boundaries round trip without float conversion');
    $beyond = $list->execute(new ListProductsInputDto(3, 2, 4));
    $assert([] === $beyond->items && 3 === $beyond->total, 'out-of-range page preserves total');
    $changed = $update->execute(new UpdateProductInputDto($first->id, 4, description: '', price: 0, printCount: 0, format: '', unit: '', staffDiscount: false, active: false));
    $assert(5 === $changed->revision && $first->id === $changed->id, 'partial update increments global revision');
    $fresh = $list->execute(new ListProductsInputDto());
    $item = $fresh->items[0];
    $assert('' === $item->description && '' === $item->format && '' === $item->unit && 0 === $item->price && 0 === $item->printCount && !$item->staffDiscount && !$item->active, 'PATCH retains explicit false zero and empty strings');
    $assert($product->details->name === $item->name && ProductKind::PHYSICAL === $item->kind && 3 === $fresh->total, 'omitted fields and disabled record preserved');
    $expect(static fn() => $update->execute(new UpdateProductInputDto($second->id, 4, name: 'Stale')), CatalogRevisionConflictException::class, 'change to another product still invalidates global revision');
    $expect(static fn() => $list->execute(new ListProductsInputDto(2, 2, 4)), CatalogRevisionConflictException::class, 'stale pagination revision rejected');
    $expect(static fn() => $update->execute(new UpdateProductInputDto('11111111-1111-4111-8111-111111111111', 5, active: false)), ProductNotFoundException::class, 'missing UUID rejected');
    $expect(static fn() => $update->execute(new UpdateProductInputDto($first->id, 5, price: -1)), InvalidProductException::class, 'invalid merged product rolls back');
    $assert(5 === $list->execute(new ListProductsInputDto())->revision, 'rejected mutations do not advance revision');

    $stage = 'atomic-rollback-and-constraints';
    $fixedIds = new class($first->id) implements ProductIdGeneratorInterface {
        public function __construct(private readonly string $id) {}

        public function generate(): ProductId
        {
            return new ProductId($this->id);
        }
    };
    $expect(static fn() => (new CreateProductUseCase($repository, $transaction, $fixedIds))->execute($product), CatalogStorageException::class, 'duplicate UUID maps storage failure and rolls back');
    $expect(static fn() => $transaction->execute(static function() use ($repository, $product): void {
        $repository->lockRevision(true);
        $repository->add(new ProductId('22222222-2222-4222-8222-222222222222'), $product->details);
        $repository->advanceRevision(5);
        throw new RuntimeException('synthetic failure after writes');
    }), CatalogStorageException::class, 'failure after both writes rolls back product and revision');
    $fresh = $list->execute(new ListProductsInputDto());
    $assert(3 === $fresh->total && 5 === $fresh->revision, 'product and global revision share one atomic transaction');
    foreach (['UF_PRICE = -1', 'UF_PRINT_COUNT = -1', 'UF_ACTIVE = 2', 'UF_STAFF_DISCOUNT = 2', "UF_KIND = 'unknown'", "UF_NAME = ''"] as $assignment) {
        $assert(false === $sql->query('UPDATE b_hlbd_mf_product SET ' . $assignment) && 3819 === $sql->errno, 'database constraint: ' . $assignment);
    }
    $expect(static fn() => $quiet(static fn() => $migration->down()), RuntimeException::class, 'rollback refuses populated catalogue');
    $quiet(static fn() => $migration->up());
    $assert(3 === $list->execute(new ListProductsInputDto())->total && 5 === $list->execute(new ListProductsInputDto())->revision, 'migration re-run preserves existing products and revision');

    $stage = 'transaction-ownership';
    $connection = Application::getConnection();
    $connection->startTransaction();
    try {
        $expect(static fn() => $list->execute(new ListProductsInputDto()), CatalogStorageException::class, 'ambient transaction rejected before entering catalogue scenario');
        $assert(5 === $repository->lockRevision(false), 'rejected nested scenario preserves caller transaction');
    } finally {
        $connection->rollbackTransaction();
    }

    $stage = 'real-concurrent-locks';
    $other = new mysqli('rabit-w02-mysql', 'root', '', 'rabit_w02');
    $other->query('SET SESSION innodb_lock_wait_timeout = 3');
    foreach ([false, true] as $exclusive) {
        $transaction->execute(static function() use ($repository, $other, $assert, $exclusive): void {
            $revision = $repository->lockRevision($exclusive);
            $other->query('UPDATE mf_catalog_state SET REVISION = REVISION WHERE ID = 1', MYSQLI_ASYNC);
            $read = [$other];
            $errors = [$other];
            $reject = [$other];
            $ready = mysqli::poll($read, $errors, $reject, 0, 150000);
            $assert(0 === $ready && 5 === $revision, ($exclusive ? 'writer' : 'list reader') . ' holds catalogue mutex against concurrent writer');
        });
        $read = [$other];
        $errors = [$other];
        $reject = [$other];
        $assert(1 === mysqli::poll($read, $errors, $reject, 2), 'waiting writer proceeds after transaction commits');
        $assert(true === $other->reap_async_query(), 'waiting writer completes without lock leak');
    }
    $other->close();
    $module->DoUninstall();
    $assert(3 === (int)$sql->query('SELECT COUNT(*) AS C FROM b_hlbd_mf_product')->fetch_assoc()['C'], 'native uninstall preserves catalogue data');
    $module->DoInstall();

    echo json_encode([
        'wave' => 'E1', 'status' => 'passed', 'php' => PHP_VERSION, 'bitrix' => $fixture['kernelVersion'],
        'mysql' => $sql->server_info, 'checks' => count($checks), 'assertions' => $checks,
        'scope' => 'Real native Bitrix Loader, DI, HL, MySQL and application use cases; no HTTP, Access, external delivery or production data.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['stage' => $stage, 'exception' => $exception::class, 'message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}
