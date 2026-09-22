<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Handoff\Application\Request\Dto\StaffTransferInputDto;
use Morefoto\Handoff\Application\Request\Service\StaffTransferGuard;
use Morefoto\Handoff\Application\Request\Service\StaffTransferPlanner;
use Morefoto\Handoff\Application\Request\UseCase\ConfirmStaffTransferUseCase;
use Morefoto\Handoff\Application\Request\UseCase\GetStaffTransferPreviewUseCase;
use Morefoto\Handoff\Domain\Request\Repository\StaffRequestRepository;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Morefoto\Handoff\Infrastructure\Database\BitrixHandoffTransaction;
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Contracts\Media\ChildTransferInterface;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Sprint\Migration\Version20260922180001;

// D3: MySQL trail of the browser transfers, an injected failure between the owners and migration safety.
if ('test' !== getenv('APP_ENV') || !is_file('/runtime/d3-transfers.json') || !is_dir('/runtime/public/bitrix')) {
    throw new RuntimeException('Transfer verification requires the disposable fixture and browser results.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
foreach (['morefoto.organization', 'morefoto.media', 'morefoto.handoff', 'morefoto.commerce'] as $module) {
    if (!Loader::includeModule($module)) {
        throw new RuntimeException('Missing verification module ' . $module);
    }
}
// Same as the E5 verifier: the migration base class is loaded for the replay check only.
Loader::includeModule('sprint.migration');
$connection = Application::getConnection();
$services = ServiceLocator::getInstance();
$fixture = json_decode((string)file_get_contents('/runtime/d3-transfers.json'), true, 8, JSON_THROW_ON_ERROR);
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('D3 verification failed: ' . $message);
    }
};
$count = static function(string $sql) use ($connection): int {
    $row = $connection->query($sql)->fetch();

    return false === $row ? 0 : (int)reset($row);
};
$proof = [];

// 1. Every transfer in the browser scenarios left one consistent trail: child, frames, row and orders agree.
$rows = $connection->query('SELECT rr.ID,rr.GROUP_ID,rr.CHILD_ID,rr.TRANSFER_GROUP_ID,rr.TRANSFER_CODE,rr.TRANSFER_PHOTO_IDS_JSON,c.GROUP_ID AS CHILD_GROUP,c.CODE
    FROM mf_staff_request_row rr INNER JOIN mf_media_child c ON c.ID=rr.CHILD_ID WHERE rr.TRANSFER_GROUP_ID IS NOT NULL')->fetchAll();
$check(2 < count($rows), 'the browser transfers are missing');
foreach ($rows as $row) {
    $check((int)$row['CHILD_GROUP'] === (int)$row['TRANSFER_GROUP_ID'] && $row['CODE'] === $row['TRANSFER_CODE'], 'child follows its transfer');
    $photos = json_decode((string)$row['TRANSFER_PHOTO_IDS_JSON'], true, 8, JSON_THROW_ON_ERROR);
    $check(is_array($photos) && [] !== $photos, 'transfer result lists the frames');
    $in = implode(',', array_map(static fn(string $id): string => "'" . $connection->getSqlHelper()->forSql($id) . "'", $photos));
    $moved = $connection->query('SELECT p.UF_GROUP_ID,p.UF_ORIGINAL_GROUP_ID,a.PUBLIC_ID AS ASSIGNMENT_ID FROM b_hlbd_mf_photo p
        LEFT JOIN mf_photo_assignment a ON a.PHOTO_ID=p.ID AND a.CHILD_ID=' . (int)$row['CHILD_ID'] . " WHERE p.UF_PUBLIC_ID IN ({$in})")->fetchAll();
    $check(count($moved) === count($photos), 'every transferred frame exists once');
    foreach ($moved as $photo) {
        $check((int)$photo['UF_GROUP_ID'] === (int)$row['TRANSFER_GROUP_ID'], 'frame moved to the target group');
        $check((int)$photo['UF_ORIGINAL_GROUP_ID'] === (int)$row['GROUP_ID'], 'original group kept');
        $check(null !== $photo['ASSIGNMENT_ID'], 'child-photo relation kept');
    }
}
$check(0 === $count('SELECT COUNT(*) FROM mf_order_line l LEFT JOIN mf_photo_assignment a ON a.PUBLIC_ID=l.ASSIGNMENT_PUBLIC_ID AND a.CHILD_ID=l.CHILD_ID
    WHERE a.PUBLIC_ID IS NULL'), 'order lines still resolve their child-photo relation');
$check(0 === $count("SELECT COUNT(*) FROM (SELECT REQUEST_ID FROM mf_staff_request_history WHERE KIND='transferred' GROUP BY REQUEST_ID HAVING COUNT(*)<>1) repeated"), 'one transfer event per request');
$check(0 === $count("SELECT COUNT(*) FROM mf_staff_request r WHERE (r.STATUS='transferred')
    <> (NOT EXISTS (SELECT 1 FROM mf_staff_request_row rr WHERE rr.REQUEST_ID=r.ID AND rr.TRANSFER_GROUP_ID IS NULL))"), 'results exist exactly for transferred requests');
$check(0 === $count('SELECT COUNT(*) FROM mf_photo_assignment a INNER JOIN mf_media_child c ON c.ID=a.CHILD_ID INNER JOIN b_hlbd_mf_photo p ON p.ID=a.PHOTO_ID
    WHERE p.UF_GROUP_ID<>c.GROUP_ID'), 'no child is separated from its frames');
$proof[] = count($rows) . ' transferred rows consistent';

// 2. A failure after the media move rolls back every owner; the same request then transfers exactly once.
$requestId = (string)$fixture['requestId'];
$snapshot = static function() use ($connection, $requestId, $fixture): array {
    $id = $connection->getSqlHelper()->forSql($requestId);
    $photo = $connection->getSqlHelper()->forSql((string)$fixture['photoId']);

    return [
        $connection->query("SELECT STATUS,REVISION FROM mf_staff_request WHERE PUBLIC_ID='{$id}'")->fetch(),
        $connection->query("SELECT c.GROUP_ID,c.CODE,p.UF_GROUP_ID,p.UF_REVISION FROM b_hlbd_mf_photo p INNER JOIN mf_photo_assignment a ON a.PHOTO_ID=p.ID
            INNER JOIN mf_media_child c ON c.ID=a.CHILD_ID WHERE p.UF_PUBLIC_ID='{$photo}'")->fetch(),
        $connection->query('SELECT s.REVISION FROM mf_media_shoot_state s INNER JOIN b_hlbd_mf_shoot sh ON sh.ID=s.SHOOT_ID WHERE sh.UF_PUBLIC_ID='
            . "'" . $connection->getSqlHelper()->forSql((string)$fixture['shootId']) . "'")->fetch(),
        $connection->query("SELECT COUNT(*) AS TOTAL FROM mf_staff_request_history h INNER JOIN mf_staff_request r ON r.ID=h.REQUEST_ID WHERE r.PUBLIC_ID='{$id}'")->fetch(),
        $connection->query("SELECT COUNT(*) AS TOTAL FROM mf_staff_request_idempotency WHERE RESOURCE_KEY='/staff-requests/{$id}/transfers'")->fetch(),
        $connection->query("SELECT COUNT(*) AS TOTAL FROM mf_staff_request_row rr INNER JOIN mf_staff_request r ON r.ID=rr.REQUEST_ID
            WHERE r.PUBLIC_ID='{$id}' AND rr.TRANSFER_GROUP_ID IS NOT NULL")->fetch(),
    ];
};
$actor = $count("SELECT ID FROM b_user WHERE LOGIN='organizer@example.invalid'");
$preview = $services->get(GetStaffTransferPreviewUseCase::class)->execute($actor, $requestId);
$input = new StaffTransferInputDto('', $preview->revision, $preview->signature);
$before = $snapshot();
$check('submitted' === $before[0]['STATUS'], 'fixture request waits for the transfer');
$real = $services->get(ChildTransferInterface::class);
$failing = new class($real) implements ChildTransferInterface {
    public function __construct(private readonly ChildTransferInterface $inner) {}

    public function sets(int $shootId, array $childIds): array
    {
        return $this->inner->sets($shootId, $childIds);
    }

    public function lockSets(int $shootId, array $childIds): array
    {
        return $this->inner->lockSets($shootId, $childIds);
    }

    public function freeCodes(int $groupId, int $count): array
    {
        return $this->inner->freeCodes($groupId, $count);
    }

    public function move(int $shootId, array $moves): int
    {
        $this->inner->move($shootId, $moves);

        throw new RuntimeException('Injected failure after the media move.');
    }
};
$broken = new ConfirmStaffTransferUseCase(
    new BitrixHandoffTransaction(),
    $services->get(GroupLinkAccessInterface::class),
    $services->get(GroupCalendarInterface::class),
    $services->get(StaffTransferGuard::class),
    $services->get(StaffRequestRepository::class),
    $services->get(StaffTransferPlanner::class),
    $failing,
);
try {
    $broken->execute($actor, $requestId, new IdempotencyKey(bin2hex(random_bytes(16))), $input);
    throw new LogicException('The injected failure must surface.');
} catch (HttpException $error) {
    $check(503 === $error->getCode(), 'failure between owners is reported as unavailable');
}
$check($before === $snapshot(), 'the injected failure left no partial transfer');
$retryKey = new IdempotencyKey(bin2hex(random_bytes(16)));
$result = $services->get(ConfirmStaffTransferUseCase::class)->execute($actor, $requestId, $retryKey, $input);
$after = $snapshot();
$check('transferred' === $result->status && 1 === count($result->results), 'retry after the failure transfers the request');
$check((int)$after[1]['UF_GROUP_ID'] === $count("SELECT ID FROM b_hlbd_mf_group WHERE UF_PUBLIC_ID='" . $connection->getSqlHelper()->forSql((string)$fixture['staffId']) . "'"), 'frame reached the staff group');
$check((int)$after[2]['REVISION'] === (int)$before[2]['REVISION'] + 1 && 1 === (int)$after[5]['TOTAL'], 'one media revision step and one recorded result');
$replay = $services->get(ConfirmStaffTransferUseCase::class)->execute($actor, $requestId, $retryKey, $input);
$check($replay == $result && $after === $snapshot(), 'replay after the retry changes nothing');
$proof[] = 'injected failure rolled back, retry transferred once';

// 3. The migration is repeatable and refuses a destructive rollback once results exist.
require_once '/app/public/local/php_interface/migrations.foundation/Version20260922180001.php';
(new Version20260922180001())->up();
$check($after === $snapshot(), 'migration replay changed data');
try {
    (new Version20260922180001())->down();
    throw new LogicException('down() must refuse while transfer results exist.');
} catch (RuntimeException $error) {
    $check(str_contains($error->getMessage(), 'destructive rollback is forbidden'), 'rollback refusal');
}
$proof[] = 'migration replay and protected down()';

echo 'D3 integration passed: ' . implode('; ', $proof) . PHP_EOL;
