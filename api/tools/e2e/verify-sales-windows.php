<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Ramsey\Uuid\Uuid;
use Sprint\Migration\Version20261011120001;

// E7: the digital deadline is filled from the original T0 for groups sent before the release, without moving print or delivery.
if ('test' !== getenv('APP_ENV') || !is_file('/runtime/e4-fixture.json')) {
    throw new RuntimeException('Sales windows verification requires the disposable fixture.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
Loader::includeModule('sprint.migration');
require_once '/app/public/local/php_interface/migrations.foundation/Version20261011120001.php';
$connection = Application::getConnection();
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('E7 verification failed: ' . $message);
    }
};
$rejected = static function(string $sql) use ($connection): bool {
    $connection->startTransaction();
    try {
        $connection->queryExecute($sql);

        return false;
    } catch (Throwable) {
        return true;
    } finally {
        $connection->rollbackTransaction();
    }
};
$dates = static function(string $groupId) use ($connection): array {
    return $connection->query("SELECT DATE_FORMAT(UF_SENT_AT,'%Y-%m-%d %H:%i:%s') S,DATE_FORMAT(UF_CLOSES_AT,'%Y-%m-%d %H:%i:%s') C,
        DATE_FORMAT(UF_DIGITAL_SALES_UNTIL,'%Y-%m-%d %H:%i:%s') D,DATE_FORMAT(UF_DELIVERY_DUE_AT,'%Y-%m-%d %H:%i:%s') L
        FROM b_hlbd_mf_group WHERE UF_PUBLIC_ID='{$groupId}'")->fetch() ?: [];
};
$fixture = json_decode((string)file_get_contents('/runtime/e4-fixture.json'), true, flags: JSON_THROW_ON_ERROR);

// 1. The browser fixture: print closed a day ago, digital six days ahead; every sent group carries the digital deadline.
$digital = $dates($fixture['digital']['groupId']);
$check([] !== $digital && $digital['C'] < gmdate('Y-m-d H:i:s') && $digital['D'] > gmdate('Y-m-d H:i:s'), 'digital window fixture');
$check('0' === (string)$connection->query('SELECT COUNT(*) N FROM b_hlbd_mf_group WHERE UF_SENT_AT IS NOT NULL AND (UF_DIGITAL_SALES_UNTIL IS NULL OR UF_DIGITAL_SALES_UNTIL<UF_CLOSES_AT)')->fetch()['N'], 'every sent group has a digital deadline not before print');

// 2. Groups sent before the release: drop the guard, write the old shape, replay the migration.
$institution = Uuid::uuid4()->toString();
$connection->queryExecute("INSERT INTO b_hlbd_mf_institution(UF_PUBLIC_ID,UF_NAME,UF_ADDRESS,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT) VALUES('{$institution}','E7 verifier','Тест',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$institutionId = (int)$connection->getInsertedId();
$shoot = Uuid::uuid4()->toString();
$connection->queryExecute("INSERT INTO b_hlbd_mf_shoot(UF_PUBLIC_ID,UF_INSTITUTION_ID,UF_NAME,UF_DATE,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT) VALUES('{$shoot}',{$institutionId},'E7 verifier',NULL,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$shootId = (int)$connection->getInsertedId();
$legacy = [
    // Sent ten days ago: closed under the old rule, digital sales reopen until T0+14.
    'regular' => "'2026-09-01 09:30:00','2026-09-08 09:30:00','2026-09-15 09:30:00'",
    // Print agreed until T0+30: digital sales never close before it.
    'extended' => "'2026-09-01 09:30:00','2026-10-01 09:30:00','2026-10-08 09:30:00'",
    'unsent' => 'NULL,NULL,NULL',
];
$groups = [];
$connection->queryExecute('ALTER TABLE b_hlbd_mf_group DROP CHECK ck_mf_group_digital_sales');
try {
    foreach ($legacy as $name => $calendar) {
        $groups[$name] = Uuid::uuid4()->toString();
        $connection->queryExecute("INSERT INTO b_hlbd_mf_group(UF_PUBLIC_ID,UF_SHOOT_ID,UF_NAME,UF_KIND,UF_TIMEZONE,UF_SENT_AT,UF_CLOSES_AT,UF_DELIVERY_DUE_AT,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT)
            VALUES('{$groups[$name]}',{$shootId},'E7 {$name}','regular','Europe/Moscow',{$calendar},1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    }
} finally {
    new Version20261011120001()->up();
}
$check(['S' => '2026-09-01 09:30:00', 'C' => '2026-09-08 09:30:00', 'D' => '2026-09-15 09:30:00', 'L' => '2026-09-15 09:30:00'] === $dates($groups['regular']), 'regular group gets T0+14, print and delivery stay');
$check(['S' => '2026-09-01 09:30:00', 'C' => '2026-10-01 09:30:00', 'D' => '2026-10-01 09:30:00', 'L' => '2026-10-08 09:30:00'] === $dates($groups['extended']), 'extended print carries digital sales with it');
$check(['S' => null, 'C' => null, 'D' => null, 'L' => null] === $dates($groups['unsent']), 'unsent group stays without deadlines');
$snapshot = array_map($dates, $groups);
new Version20261011120001()->up();
$check($snapshot === array_map($dates, $groups), 'replaying the migration changes nothing');
$check($rejected("UPDATE b_hlbd_mf_group SET UF_DIGITAL_SALES_UNTIL=NULL WHERE UF_PUBLIC_ID='{$groups['regular']}'"), 'MySQL rejects a sent group without the digital deadline');
$check($rejected("UPDATE b_hlbd_mf_group SET UF_DIGITAL_SALES_UNTIL=UF_CLOSES_AT-INTERVAL 1 SECOND WHERE UF_PUBLIC_ID='{$groups['regular']}'"), 'MySQL rejects digital sales closing before print');

$connection->queryExecute("DELETE FROM b_hlbd_mf_group WHERE UF_SHOOT_ID={$shootId}");
$connection->queryExecute("DELETE FROM b_hlbd_mf_shoot WHERE ID={$shootId}");
$connection->queryExecute("DELETE FROM b_hlbd_mf_institution WHERE ID={$institutionId}");

echo "E7 sales windows integration passed\n";
