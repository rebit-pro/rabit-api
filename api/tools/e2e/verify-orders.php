<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Commerce\Application\Order\Contract\OrderStaffAccessInterface;
use Morefoto\Commerce\Application\Order\Dto\CreateOrderInputDto;
use Morefoto\Commerce\Application\Order\Dto\OrderBuyerInputDto;
use Morefoto\Commerce\Application\Order\Dto\SearchOrdersInputDto;
use Morefoto\Commerce\Application\Order\Service\OrderAccessKeys;
use Morefoto\Commerce\Application\Order\UseCase\CreateOrderUseCase;
use Morefoto\Commerce\Application\Order\UseCase\GetBuyerOrderUseCase;
use Morefoto\Commerce\Application\Order\UseCase\SearchStaffOrdersUseCase;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteLineInputDto;
use Morefoto\Commerce\Application\Storefront\UseCase\CreateQuoteUseCase;
use Morefoto\Commerce\Domain\Order\ValueObject\IdempotencyKey;
use Morefoto\Media\Application\Gallery\Service\GalleryCapabilityLifecycle;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Sprint\Migration\Version20260922120001;

if ('test' !== getenv('APP_ENV') || !is_file('/runtime/e5-orders.json') || !is_file('/runtime/e4-fixture.json')) {
    throw new RuntimeException('Order verification requires the disposable fixture and browser results.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
Loader::includeModule('morefoto.commerce');
Loader::includeModule('sprint.migration');
$services = ServiceLocator::getInstance();
$connection = Application::getConnection();
$fixture = json_decode((string)file_get_contents('/runtime/e4-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$secrets = json_decode((string)file_get_contents('/runtime/e5-orders.json'), true, flags: JSON_THROW_ON_ERROR);
$expect = static function(string $code, callable $operation): void {
    try {
        $operation();
    } catch (HttpException $error) {
        if ($code === $error->getMessage()) {
            return;
        }
        throw new RuntimeException('Expected ' . $code . ', got ' . $error->getMessage());
    }
    throw new RuntimeException('Expected rejection: ' . $code);
};
$scalar = static fn(string $sql): int => (int)current((array)$connection->query($sql)->fetch());
$proof = [];

// 1. Secrets exist only as hashes or in the sealed replay copy.
if (count($secrets['accessKeys']) < 8 || [] === $secrets['idempotencyKeys']) {
    throw new RuntimeException('Browser scenarios did not record enough orders.');
}
$dump = '';
foreach (['mf_order', 'mf_order_line', 'mf_order_access_key', 'mf_order_checkout', 'mf_cart_quote'] as $table) {
    $result = $connection->query('SELECT * FROM ' . $table);
    while (false !== ($row = $result->fetch())) {
        $dump .= json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
foreach ([...$secrets['accessKeys'], ...$secrets['idempotencyKeys'], $secrets['galleryToken']] as $secret) {
    if (str_contains($dump, (string)$secret)) {
        throw new RuntimeException('A raw key or token is stored in clear text.');
    }
}
$hashes = implode("','", array_map(static fn(string $key): string => hash('sha256', $key), array_unique($secrets['accessKeys'])));
if (count(array_unique($secrets['accessKeys'])) !== $scalar("SELECT COUNT(*) FROM mf_order_access_key WHERE KEY_HASH IN ('{$hashes}')")) {
    throw new RuntimeException('Issued keys must be stored as SHA-256 only.');
}
$proof[] = 'no raw secrets';

// 2. One order per quote, no leftovers of failed attempts, every order has one active key and one receipt.
if (0 !== $scalar('SELECT COUNT(*) FROM (SELECT QUOTE_HASH FROM mf_order GROUP BY QUOTE_HASH HAVING COUNT(*) > 1) duplicate')
    || 0 !== $scalar('SELECT COUNT(*) FROM mf_order_checkout WHERE ORDER_ID IS NULL')
    || 0 !== $scalar('SELECT COUNT(*) FROM mf_order o LEFT JOIN mf_order_checkout c ON c.ORDER_ID=o.ID WHERE c.ORDER_ID IS NULL')
    || $scalar('SELECT COUNT(*) FROM mf_order') !== $scalar('SELECT COUNT(*) FROM mf_order_access_key WHERE REVOKED_AT IS NULL')
    || count(array_unique($secrets['orderIds'])) !== $scalar('SELECT COUNT(*) FROM mf_order')) {
    throw new RuntimeException('Order, key and receipt invariants are broken.');
}
$proof[] = 'one order per quote and receipt';

// 3. Personal key lifecycle: revoke, expiry, reissue and independence from the gallery link.
$buyer = $services->get(GetBuyerOrderUseCase::class);
$keys = $services->get(OrderAccessKeys::class);
$key = $secrets['accessKeys'][0];
$order = $buyer->execute($key)->order;
$orderRow = $connection->query("SELECT ID FROM mf_order WHERE PUBLIC_ID='{$order->id}'")->fetch();
$orderId = (int)$orderRow['ID'];
$lifecycle = static function(callable $check) use ($connection): void {
    $connection->startTransaction();
    try {
        $check();
    } finally {
        $connection->rollbackTransaction();
    }
};
$lifecycle(static function() use ($keys, $buyer, $orderId, $key, $expect): void {
    if (!$keys->revoke($orderId, new DateTimeImmutable(), 'revoked')) {
        throw new RuntimeException('Active key was not revoked.');
    }
    $expect('ORDER_NOT_FOUND', static fn() => $buyer->execute($key));
    $replacement = $keys->issue($orderId, new DateTimeImmutable(), 'recovery');
    if ('' === $buyer->execute($replacement->key)->order->id) {
        throw new RuntimeException('Reissued key does not open the order.');
    }
    $expect('ORDER_NOT_FOUND', static fn() => $buyer->execute($key));
});
$lifecycle(static function() use ($connection, $buyer, $key, $expect): void {
    $hash = hash('sha256', $key);
    $connection->queryExecute("UPDATE mf_order_access_key SET EXPIRES_AT=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE KEY_HASH='{$hash}'");
    $expect('ORDER_NOT_FOUND', static fn() => $buyer->execute($key));
});
$lifecycle(static function() use ($services, $fixture, $buyer, $key): void {
    $gallery = $services->get(GalleryAccessInterface::class)->context($fixture['open']['token']);
    $services->get(GalleryCapabilityLifecycle::class)->revoke($fixture['open']['token'], $gallery->capabilityRevision);
    $buyer->execute($key);
});
if ($buyer->execute($key)->order->id !== $order->id) {
    throw new RuntimeException('Rolled back lifecycle checks changed the key.');
}
$proof[] = 'key revoke, expiry, reissue and gallery independence';

// 4. Replay after closure returns the same order; a new order and an expired quote are rejected.
$gallery = $services->get(GalleryAccessInterface::class)->resolve($fixture['open']['token']);
$product = $connection->query("SELECT PRODUCT_PUBLIC_ID FROM mf_order_line WHERE PRODUCT_KIND='physical' LIMIT 1")->fetch();
$lines = [new QuoteLineInputDto($gallery->assignments[0]->assignmentId, (string)$product['PRODUCT_PUBLIC_ID'], 5)];
$quote = $services->get(CreateQuoteUseCase::class)->execute($fixture['open']['token'], $lines);
$input = new CreateOrderInputDto($quote->quoteToken, $lines, new OrderBuyerInputDto('Проверка повтора', '+79005550909', 'replay.e5@example.test', '', null, true));
$replayKey = new IdempotencyKey(bin2hex(random_bytes(16)));
$checkout = $services->get(CreateOrderUseCase::class);
$created = $checkout->execute($fixture['open']['token'], $replayKey, $input);
$groupId = $fixture['open']['groupId'];
$closesAt = (string)$connection->query("SELECT DATE_FORMAT(UF_CLOSES_AT,'%Y-%m-%d %H:%i:%s') AS C FROM b_hlbd_mf_group WHERE UF_PUBLIC_ID='{$groupId}'")->fetch()['C'];
$connection->queryExecute("UPDATE b_hlbd_mf_group SET UF_CLOSES_AT=UTC_TIMESTAMP()-INTERVAL 1 MINUTE WHERE UF_PUBLIC_ID='{$groupId}'");
try {
    $replayed = $checkout->execute($fixture['open']['token'], $replayKey, $input);
    if ($replayed->order->id !== $created->order->id || $replayed->accessKey !== $created->accessKey) {
        throw new RuntimeException('Replay after closure must return the original order and key.');
    }
    $expect('GALLERY_CLOSED', static fn() => $checkout->execute($fixture['open']['token'], new IdempotencyKey(bin2hex(random_bytes(16))), $input));
} finally {
    $connection->queryExecute("UPDATE b_hlbd_mf_group SET UF_CLOSES_AT='{$closesAt}' WHERE UF_PUBLIC_ID='{$groupId}'");
}
$expired = $services->get(CreateQuoteUseCase::class)->execute($fixture['open']['token'], $lines);
$expiredHash = hash('sha256', $expired->quoteToken);
$connection->queryExecute("UPDATE mf_cart_quote SET EXPIRES_AT=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE TOKEN_HASH='{$expiredHash}'");
$expect('QUOTE_EXPIRED', static fn() => $checkout->execute(
    $fixture['open']['token'],
    new IdempotencyKey(bin2hex(random_bytes(16))),
    new CreateOrderInputDto($expired->quoteToken, $lines, $input->buyer),
));
$proof[] = 'replay after closure, closed group and expired quote';

// 5. A change of the curator's rights during a read is rejected.
$curator = (int)$connection->query("SELECT ID FROM b_user WHERE LOGIN='curator@example.invalid' OR EMAIL='curator@example.invalid' LIMIT 1")->fetch()['ID'];
$access = $services->get(OrderStaffAccessInterface::class);
$scope = $access->scope($curator);
$lifecycle(static function() use ($connection, $access, $curator, $scope, $expect): void {
    $connection->queryExecute("UPDATE b_hlbd_mf_staff_profile SET UF_ACCESS_REVISION=UF_ACCESS_REVISION+1 WHERE UF_USER_ID={$curator}");
    $expect('ACCESS_CHANGED', static fn() => $access->assertUnchanged($curator, $scope));
});
$proof[] = 'access change during read';

// 6. Migration replay is harmless and rollback refuses to drop orders.
require_once '/app/public/local/php_interface/migrations.foundation/Version20260922120001.php';
$before = $scalar('SELECT COUNT(*) FROM mf_order');
(new Version20260922120001())->up();
if ($before !== $scalar('SELECT COUNT(*) FROM mf_order')) {
    throw new RuntimeException('Migration replay changed orders.');
}
try {
    (new Version20260922120001())->down();
    throw new LogicException('down() must refuse while orders exist.');
} catch (RuntimeException $error) {
    if (!str_contains($error->getMessage(), 'Orders exist')) {
        throw $error;
    }
}
$proof[] = 'migration replay and protected down()';

// 7. Staff search on a realistic volume: scope and filters in SQL, a fixed number of queries per page.
$organizer = (int)$connection->query("SELECT ID FROM b_user WHERE LOGIN='organizer@example.invalid' OR EMAIL='organizer@example.invalid' LIMIT 1")->fetch()['ID'];
$source = $connection->query('SELECT * FROM mf_order ORDER BY ID LIMIT 1')->fetch();
$connection->startTransaction();
try {
    $connection->queryExecute("INSERT INTO mf_cart_quote(TOKEN_HASH,GALLERY_HASH,FINGERPRINT,SNAPSHOT_JSON,EXPIRES_AT,CREATED_AT)
        WITH RECURSIVE seq(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM seq WHERE n<1000)
        SELECT SHA2(CONCAT('e5-volume-', n),256),'{$source['GALLERY_HASH']}',REPEAT('0',64),'{}',UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM seq");
    $connection->queryExecute("INSERT INTO mf_order(PUBLIC_ID,NUMBER,GALLERY_HASH,QUOTE_HASH,INSTITUTION_ID,INSTITUTION_PUBLIC_ID,SHOOT_ID,SHOOT_PUBLIC_ID,
        GROUP_ID,GROUP_PUBLIC_ID,AUDIENCE,INSTITUTION_NAME,SHOOT_NAME,GROUP_NAME,BUYER_NAME,BUYER_PHONE,BUYER_EMAIL,BUYER_COMMENT,RECEIPT_CHANNEL,
        SUBTOTAL,DISCOUNT,GIFT_SAVING,TOTAL,ITEM_COUNT,GIFTS,CATALOG_REVISION,CONDITIONS_REVISION,PAYMENT_STATUS,PRODUCTION_STATUS,VERSION,CREATED_AT,UPDATED_AT)
        WITH RECURSIVE seq(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM seq WHERE n<1000)
        SELECT UUID(),CONCAT('MF-V',LPAD(n,6,'0')),GALLERY_HASH,SHA2(CONCAT('e5-volume-', n),256),INSTITUTION_ID,INSTITUTION_PUBLIC_ID,SHOOT_ID,
        SHOOT_PUBLIC_ID,GROUP_ID,GROUP_PUBLIC_ID,AUDIENCE,INSTITUTION_NAME,SHOOT_NAME,GROUP_NAME,CONCAT('Покупатель ',n),BUYER_PHONE,
        CONCAT('volume',n,'@example.test'),'',NULL,SUBTOTAL,DISCOUNT,GIFT_SAVING,TOTAL,ITEM_COUNT,GIFTS,CATALOG_REVISION,CONDITIONS_REVISION,
        PAYMENT_STATUS,PRODUCTION_STATUS,1,UTC_TIMESTAMP()-INTERVAL n MINUTE,UTC_TIMESTAMP() FROM seq JOIN mf_order WHERE mf_order.ID={$source['ID']}");
    $connection->queryExecute("INSERT INTO mf_order_line(PUBLIC_ID,ORDER_ID,LINE_NO,ASSIGNMENT_PUBLIC_ID,CHILD_ID,CHILD_PUBLIC_ID,CHILD_CODE,PHOTO_PUBLIC_ID,
        PHOTO_CODE,PHOTO_WIDTH,PHOTO_HEIGHT,PRODUCT_PUBLIC_ID,PRODUCT_KIND,PRODUCT_NAME,PRODUCT_DESCRIPTION,PRODUCT_FORMAT,PRODUCT_UNIT,PRODUCT_PRICE,
        PRINT_COUNT,STAFF_DISCOUNT,QUANTITY,UNIT_PRICE,DISCOUNT,TOTAL,COVERED_BY_GIFT)
        SELECT UUID(),o.ID,1,l.ASSIGNMENT_PUBLIC_ID,l.CHILD_ID,l.CHILD_PUBLIC_ID,l.CHILD_CODE,l.PHOTO_PUBLIC_ID,l.PHOTO_CODE,l.PHOTO_WIDTH,l.PHOTO_HEIGHT,
        l.PRODUCT_PUBLIC_ID,l.PRODUCT_KIND,l.PRODUCT_NAME,l.PRODUCT_DESCRIPTION,l.PRODUCT_FORMAT,l.PRODUCT_UNIT,l.PRODUCT_PRICE,l.PRINT_COUNT,
        l.STAFF_DISCOUNT,l.QUANTITY,l.UNIT_PRICE,l.DISCOUNT,l.TOTAL,l.COVERED_BY_GIFT
        FROM mf_order o JOIN mf_order_line l ON l.ORDER_ID={$source['ID']} AND l.LINE_NO=1 WHERE o.NUMBER LIKE 'MF-V%'");
    $total = $scalar('SELECT COUNT(*) FROM mf_order');
    $search = $services->get(SearchStaffOrdersUseCase::class);
    // The number of SQL statements must not depend on the page size: no N+1 over orders or lines.
    $measure = static function(int $pageSize) use ($connection, $search, $organizer): array {
        $tracker = $connection->startTracker(true);
        $started = hrtime(true);
        $page = $search->execute($organizer, new SearchOrdersInputDto(null, null, null, null, 'unpaid', null, null, null, 2, $pageSize));
        $elapsed = (hrtime(true) - $started) / 1e6;
        $queries = count($tracker->getQueries());
        $connection->stopTracker();

        return [$page, $queries, $elapsed];
    };
    [$small, $smallQueries] = $measure(10);
    [$large, $largeQueries, $elapsed] = $measure(100);
    $filtered = $search->execute($organizer, new SearchOrdersInputDto('volume11', null, null, $fixture['open']['groupId'], null, null, null, null, 1, 100));
    if ($smallQueries !== $largeQueries || $largeQueries > 15 || 10 !== count($small->items) || 100 !== count($large->items)
        || $large->total !== $total || 11 > $filtered->total) {
        throw new RuntimeException(sprintf('Volume search is wrong or grows with the page: %d vs %d SQL.', $smallQueries, $largeQueries));
    }
    $proof[] = sprintf('search on %d orders: %d SQL for 10 and 100 rows, %.1f ms per page of 100', $total, $largeQueries, $elapsed);
} finally {
    $connection->rollbackTransaction();
}

echo 'E5 integration passed: ' . implode('; ', $proof) . ".\n";
