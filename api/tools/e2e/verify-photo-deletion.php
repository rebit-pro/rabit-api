<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Media\Application\Photo\Dto\DeletePhotosInputDto;
use Morefoto\Media\Application\Photo\UseCase\DeleteGroupPhotosUseCase;
use Morefoto\Media\Domain\Photo\ValueObject\IdempotencyKey;
use Rebit\Share\Shared\Exception\HttpException;

// #116: the photo deletion SQL on MySQL. The browser deleted a canonical frame referenced by a duplicate record; here
// the rows and files it left are checked, then a ready + processing set goes through the real use case and must be
// refused whole with PHOTO_PROCESSING.
if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix')) {
    throw new RuntimeException('Photo deletion verification requires the disposable test runtime.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
if (!Loader::includeModule('morefoto.media')) {
    throw new RuntimeException('Missing verification module morefoto.media');
}
$connection = Application::getConnection();
$quote = static fn(string $value): string => "'" . $connection->getSqlHelper()->forSql($value) . "'";
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('#116 verification failed: ' . $message);
    }
};
/** @var array{shootId: string, groupId: string, keep: string, busy: string, canonical: string, duplicate: string, fingerprint: string} $scenario */
$scenario = json_decode((string)file_get_contents('/runtime/i116-deletion.json'), true, 4, JSON_THROW_ON_ERROR);
$group = $connection->query('SELECT ID,UF_SHOOT_ID FROM b_hlbd_mf_group WHERE UF_PUBLIC_ID=' . $quote($scenario['groupId']))->fetchRaw();
$check(is_array($group), 'the browser group exists');
$groupId = (int)$group['ID'];
$shootId = (int)$group['UF_SHOOT_ID'];

$photos = static function() use ($connection, $shootId): array {
    $result = $connection->query('SELECT ID,UF_PUBLIC_ID,UF_GROUP_ID,UF_STATUS,UF_JOB_STATE,UF_EXISTING_PHOTO_ID,UF_ORIGINAL_PATH,UF_THUMB_SRC,UF_PREVIEW_SRC,UF_REVISION'
        . ' FROM b_hlbd_mf_photo WHERE UF_SHOOT_ID=' . $shootId . ' ORDER BY ID');
    $rows = [];
    while (false !== ($row = $result->fetchRaw())) {
        $rows[(string)$row['UF_PUBLIC_ID']] = $row;
    }

    return $rows;
};
$assignments = static function() use ($connection, $groupId): array {
    $result = $connection->query('SELECT p.UF_PUBLIC_ID AS PHOTO,c.CODE,a.SEQUENCE_NO,a.PUBLIC_ID FROM mf_photo_assignment a'
        . ' INNER JOIN mf_media_child c ON c.ID=a.CHILD_ID INNER JOIN b_hlbd_mf_photo p ON p.ID=a.PHOTO_ID'
        . ' WHERE c.GROUP_ID=' . $groupId . ' ORDER BY c.CODE,a.SEQUENCE_NO');
    $rows = [];
    while (false !== ($row = $result->fetchRaw())) {
        $rows[] = $row;
    }

    return $rows;
};
$cover = static function() use ($connection, $groupId): ?string {
    $row = $connection->query('SELECT p.UF_PUBLIC_ID FROM mf_media_group_cover cover INNER JOIN b_hlbd_mf_photo p ON p.ID=cover.PHOTO_ID'
        . ' WHERE cover.GROUP_ID=' . $groupId)->fetchRaw();

    return false === $row ? null : (string)$row['UF_PUBLIC_ID'];
};
$revision = static fn(): int => (int)$connection->query('SELECT REVISION FROM mf_media_shoot_state WHERE SHOOT_ID=' . $shootId)->fetchRaw()['REVISION'];
$privateRoot = rtrim((string)getenv('MOREFOTO_PRIVATE_MEDIA_PATH'), '/');
$previewRoot = rtrim((string)getenv('MOREFOTO_PUBLIC_PREVIEW_PATH'), '/');
$previews = static fn(string $photoId): array => array_map(
    static fn(string $variant): string => $previewRoot . '/' . substr($photoId, 0, 2) . '/' . $photoId . '-' . $variant . '.webp',
    ['thumb', 'preview'],
);
/** Existence of the original and both previews of every remaining frame. */
$files = static function(array $rows) use ($privateRoot, $previews): array {
    $state = [];
    foreach ($rows as $photoId => $row) {
        foreach ([$privateRoot . '/' . $row['UF_ORIGINAL_PATH'], ...$previews((string)$photoId)] as $path) {
            $state[$path] = is_file($path);
        }
    }

    return $state;
};

// 1. T08: the canonical frame left together with its duplicate record; the other frames, labels and cover are intact.
$rows = $photos();
$remaining = array_keys($rows);
sort($remaining);
$expected = [$scenario['keep'], $scenario['busy']];
sort($expected);
$check($expected === $remaining, 'only the kept frames remain in the shoot: ' . implode(',', $remaining));
foreach ($rows as $photoId => $row) {
    $check('ready' === $row['UF_STATUS'] && null === $row['UF_EXISTING_PHOTO_ID'] && $groupId === (int)$row['UF_GROUP_ID'], $photoId . ' is a ready frame of the group');
}
$orphans = $connection->query('SELECT COUNT(*) AS TOTAL FROM b_hlbd_mf_photo d LEFT JOIN b_hlbd_mf_photo c ON c.ID=d.UF_EXISTING_PHOTO_ID'
    . ' WHERE d.UF_EXISTING_PHOTO_ID IS NOT NULL AND c.ID IS NULL')->fetchRaw();
$check(0 === (int)$orphans['TOTAL'], 'no duplicate record points to a missing frame');
$labels = array_map(static fn(array $row): array => [(string)$row['PHOTO'], (string)$row['CODE']], $assignments());
$check([[$scenario['keep'], 'A'], [$scenario['busy'], 'B']] === $labels, 'labels of the kept frames stay: ' . json_encode($labels));
$check($scenario['keep'] === $cover(), 'the cover moved to the remaining labelled frame');
$fingerprint = $scenario['fingerprint'];
$check(1 === preg_match('/^[a-f0-9]{64}$/D', $fingerprint), 'the browser recorded the canonical fingerprint');
$original = $privateRoot . '/' . $scenario['shootId'] . '/' . substr($fingerprint, 0, 2) . '/' . $fingerprint . '.png';
$check(!is_file($original), 'the unused original of the deleted frame is removed');
foreach ($previews($scenario['canonical']) as $path) {
    $check(!is_file($path), basename($path) . ' of the deleted frame is removed');
}
$filesBefore = $files($rows);
$check(!in_array(false, $filesBefore, true), 'originals and previews of the kept frames are on disk');

// 2. T10: a frame still in render (job published to the worker) makes the whole set fail before any change.
$organizer = $connection->query("SELECT ID FROM b_user WHERE EMAIL='organizer@example.invalid'")->fetchRaw();
$check(is_array($organizer), 'the organizer account exists');
$labelsBefore = $assignments();
$revisionBefore = $revision();
$connection->queryExecute("UPDATE b_hlbd_mf_photo SET UF_STATUS='processing',UF_JOB_STATE='published' WHERE UF_PUBLIC_ID="
    . $quote($scenario['busy']) . " AND UF_STATUS='ready'");
$check(1 === $connection->getAffectedRowsCount(), 'the frame is held in processing');
$key = bin2hex(random_bytes(16));
try {
    $held = $photos();
    try {
        ServiceLocator::getInstance()->get(DeleteGroupPhotosUseCase::class)->execute(
            (int)$organizer['ID'],
            $scenario['groupId'],
            new IdempotencyKey($key),
            new DeletePhotosInputDto($revisionBefore, [$scenario['keep'], $scenario['busy']]),
        );
        $check(false, 'a set with a processing frame was deleted');
    } catch (HttpException $error) {
        $check('PHOTO_PROCESSING' === $error->getMessage() && 409 === $error->getCode(), 'the refusal is 409 PHOTO_PROCESSING, got ' . $error->getMessage());
    }
    $check($held === $photos(), 'no photo row changed');
    $check($labelsBefore === $assignments(), 'no label changed');
    $check($scenario['keep'] === $cover(), 'the cover did not change');
    $check($revisionBefore === $revision(), 'the media revision did not advance');
    $check($filesBefore === $files($rows), 'no original or preview was removed');
    $stored = $connection->query('SELECT COUNT(*) AS TOTAL FROM mf_media_idempotency WHERE IDEMPOTENCY_KEY=' . $quote($key))->fetchRaw();
    $check(0 === (int)$stored['TOTAL'], 'the refused request left no idempotency record');
} finally {
    $connection->queryExecute("UPDATE b_hlbd_mf_photo SET UF_STATUS='ready',UF_JOB_STATE='done' WHERE UF_PUBLIC_ID="
        . $quote($scenario['busy']) . " AND UF_STATUS='processing'");
}
$check($rows === $photos(), 'the held frame is ready again');

echo "#116 photo deletion integration passed\n";
