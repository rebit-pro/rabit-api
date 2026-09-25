<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Morefoto\Handoff\Domain\Request\Repository\StaffRequestRepository;
use Rebit\Share\Contracts\Access\Dto\StaffRequestActorOutputDto;

// #26: HND-06 on 1000 staff requests in MySQL. The set lives in a transaction that is always rolled back.
if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix')) {
    throw new RuntimeException('Handoff list verification requires the disposable test runtime.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
foreach (['morefoto.organization', 'morefoto.media', 'morefoto.handoff'] as $module) {
    if (!Loader::includeModule($module)) {
        throw new RuntimeException('Missing verification module ' . $module);
    }
}
$connection = Application::getConnection();
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('F1 list verification failed: ' . $message);
    }
};
// The F1 browser scenario left a request with a real institution, shoot, group and child to copy.
$source = $connection->query('SELECT r.INSTITUTION_ID,r.SHOOT_ID,rr.GROUP_ID,rr.CHILD_ID FROM mf_staff_request r
    INNER JOIN mf_staff_request_row rr ON rr.REQUEST_ID=r.ID ORDER BY r.ID LIMIT 1')->fetch();
$check(is_array($source), 'the F1 browser request is missing');
$size = 1000;
$repository = new StaffRequestRepository();
$organizer = new StaffRequestActorOutputDto(1, 'E2E', 'organizer', 1, [], []);
$measure = static function(callable $read) use ($connection): array {
    gc_collect_cycles();
    $memory = memory_get_usage();
    $tracker = $connection->startTracker(true);
    $started = hrtime(true);
    $result = $read();
    $seconds = (hrtime(true) - $started) / 1e9;
    $connection->stopTracker();

    return [$result, ['queries' => count($tracker->getQueries()), 'ms' => round($seconds * 1000, 1), 'memoryKb' => intdiv(memory_get_usage() - $memory, 1024)]];
};
$proof = [];
$connection->startTransaction();
try {
    $values = [];
    for ($index = 1; $index <= $size; ++$index) {
        $values[] = sprintf(
            "('e2e26000-0000-4000-8000-%012d',%d,%d,1,'E2E',%s,1,'',1,'verified_staff_assignment',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP() - INTERVAL %d SECOND)",
            $index,
            (int)$source['INSTITUTION_ID'],
            (int)$source['SHOOT_ID'],
            0 === $index % 3 ? "'clarification'" : "'submitted'",
            $index,
        );
    }
    $connection->queryExecute('INSERT INTO mf_staff_request(PUBLIC_ID,INSTITUTION_ID,SHOOT_ID,CREATED_BY,CREATED_BY_NAME,STATUS,REVISION,COMMENT,'
        . 'STAFF_ELIGIBLE,ELIGIBILITY_SOURCE,ELIGIBILITY_VERIFIED_AT,CREATED_AT,UPDATED_AT) VALUES' . implode(',', $values));
    $connection->queryExecute(sprintf(
        "INSERT INTO mf_staff_request_row(PUBLIC_ID,REQUEST_ID,GROUP_ID,CHILD_ID,INPUT_CODE,PHOTO_IDS_JSON,SORT_NO,CREATED_AT)
        SELECT CONCAT('e2e26001',SUBSTRING(r.PUBLIC_ID,9)),r.ID,%d,%d,'A','[]',1,UTC_TIMESTAMP() FROM mf_staff_request r WHERE r.PUBLIC_ID LIKE 'e2e26000-%%'",
        (int)$source['GROUP_ID'],
        (int)$source['CHILD_ID'],
    ));
    $connection->queryExecute("INSERT INTO mf_staff_request_history(REQUEST_ID,KIND,ACTOR_ID,ACTOR_NAME,COMMENT,CONFIRMED,CREATED_AT)
        SELECT r.ID,'submitted',1,'E2E','',1,UTC_TIMESTAMP() FROM mf_staff_request r WHERE r.PUBLIC_ID LIKE 'e2e26000-%'");

    // 1. The SQL count of a page does not depend on the page size; related rows and history belong to their request.
    foreach ([1, 25, 100] as $pageSize) {
        [$page, $proof['page' . $pageSize]] = $measure(static fn(): array => $repository->page($organizer, null, null, null, $pageSize, 0));
        $check($pageSize === count($page['items']) && $size <= $page['total'], 'page of ' . $pageSize);
        $check(4 === $proof['page' . $pageSize]['queries'], 'page of ' . $pageSize . ' used ' . $proof['page' . $pageSize]['queries'] . ' queries');
        foreach ($page['items'] as $item) {
            $check(1 <= count($item['rows']) && 1 <= count($item['history']), 'rows and history of ' . $item['id']);
        }
    }
    [$last, $proof['lastPage100']] = $measure(static fn(): array => $repository->page($organizer, null, null, null, 100, 900));
    $check(100 === count($last['items']) && 4 === $proof['lastPage100']['queries'], 'a deep page keeps the query count');
    [$clarifications] = $measure(static fn(): array => $repository->page($organizer, null, null, 'clarification', 100, 0));
    $check($clarifications['total'] === $clarifications['byStatus']['clarification'] && 333 <= $clarifications['total'], 'status filter and total');

    // 2. The same 100 cards read one by one, as HND-08 does, for comparison with the page.
    [, $proof['oneByOne100']] = $measure(static function() use ($repository, $page): int {
        foreach ($page['items'] as $item) {
            $repository->view($repository->request($item['id']) ?? throw new RuntimeException('card vanished'));
        }

        return count($page['items']);
    });
    $check(300 === $proof['oneByOne100']['queries'], 'one-by-one reading is the reference');
} finally {
    $connection->rollbackTransaction();
}
$check(false === $connection->query("SELECT 1 FROM mf_staff_request WHERE PUBLIC_ID LIKE 'e2e26000-%' LIMIT 1")->fetch(), 'the synthetic set was rolled back');

echo json_encode($proof, JSON_THROW_ON_ERROR), PHP_EOL;
echo "F1 list integration passed\n";
