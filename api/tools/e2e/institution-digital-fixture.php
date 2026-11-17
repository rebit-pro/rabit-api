<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Commerce\Application\Order\Service\OrderAccessKeys;
use Morefoto\Media\Application\Photo\Contract\PrivatePhotoStorageInterface;
use Morefoto\Media\Application\Photo\Dto\InspectedPhoto;
use Morefoto\Media\Application\Photo\Message\Handler\ProcessPhotoMessageHandler;
use Morefoto\Media\Application\Photo\Message\ProcessPhotoMessage;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;

if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix') || !is_file('/runtime/digital-request.json')) {
    throw new RuntimeException('Digital archive fixture requires the disposable runtime and a browser request.');
}
// Media files must have the same owner as the real worker, including fixture-created originals and previews.
if (0 === posix_geteuid()) {
    $worker = posix_getpwnam('www-data');
    if (false === $worker || !posix_setgid($worker['gid']) || !posix_setuid($worker['uid'])) {
        throw new RuntimeException('Cannot use the Media worker identity.');
    }
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
if (!Loader::includeModule('morefoto.files')) {
    throw new RuntimeException('Cannot load isolated Files providers.');
}
require_once __DIR__ . '/institution-production-fixture.php';
$request = json_decode((string)file_get_contents('/runtime/digital-request.json'), true, flags: JSON_THROW_ON_ERROR);
$db = Application::getConnection();
$services = ServiceLocator::getInstance();
$text = static fn(string $value): string => "'" . $db->getSqlHelper()->forSql($value) . "'";
$uuid = static fn(): string => Uuid::uuid4()->toString();
$path = '/runtime/institution-digital-fixture.json';
/** Historical snapshots use the existing L1 helper: own quote, checkout/key, consents, attempt and money fact; no external payment/mail. */
$clone = static function(string $table, array $row, array $changes) use ($db, $text): int {
    unset($row['ID']);
    foreach ($changes as $column => $value) {
        if (!array_key_exists($column, $row)) {
            throw new RuntimeException('Unknown fixture column.');
        }
        $row[$column] = $value;
    }
    $columns = implode(',', array_map(static fn(string $column): string => '`' . $column . '`', array_keys($row)));
    $values = implode(',', array_map(static fn(mixed $value): string => null === $value ? 'NULL' : $text((string)$value), array_values($row)));
    $db->queryExecute('INSERT INTO ' . $table . '(' . $columns . ') VALUES(' . $values . ')');

    return (int)$db->getInsertedId();
};
$state = is_file($path) ? json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) : null;
if ('setup' === $request['action'] && null === $state) {
    $sourceGroup = $text((string)$request['sourceGroup']);
    $source = $db->query("SELECT ID FROM mf_order WHERE GROUP_PUBLIC_ID={$sourceGroup} AND PAYMENT_STATUS='paid' AND LATE_PAYMENT=0 ORDER BY ID LIMIT 1")->fetchRaw();
    if (false === $source) {
        throw new RuntimeException('A real paid J4 order must precede the digital history fixture.');
    }
    $institutionId = $uuid();
    $db->queryExecute('INSERT INTO b_hlbd_mf_institution(UF_PUBLIC_ID,UF_NAME,UF_ADDRESS,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT) VALUES('
        . $text($institutionId) . ",'Электронный архив E2E','Тестовый адрес',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    $institutionNative = (int)$db->getInsertedId();
    $shootId = $uuid();
    $db->queryExecute('INSERT INTO b_hlbd_mf_shoot(UF_PUBLIC_ID,UF_INSTITUTION_ID,UF_NAME,UF_DATE,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT) VALUES('
        . $text($shootId) . ",{$institutionNative},'Съёмка электронной выгрузки',NULL,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    $shootNative = (int)$db->getInsertedId();
    $curator = (int)$db->query("SELECT ID FROM b_user WHERE EMAIL='curator@example.invalid'")->fetchRaw()['ID'];
    $db->queryExecute("INSERT INTO b_hlbd_mf_institution_assignment(UF_INSTITUTION_ID,UF_ROLE,UF_USER_ID,UF_CREATED_AT,UF_UPDATED_AT)
        VALUES({$institutionNative},'curator',{$curator},UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    $state = ['institutionId' => $institutionId, 'institutionNativeId' => $institutionNative, 'shootId' => $shootId,
        'shootNativeId' => $shootNative, 'sourceOrderNativeId' => (int)$source['ID'], 'curatorId' => $curator,
        'groups' => [], 'orderIds' => [], 'expectedPhotos' => [], 'excludedPhotos' => [], 'originalPaths' => []];
    for ($index = 0; 2 > $index; ++$index) {
        $groupId = $uuid();
        $db->queryExecute('INSERT INTO b_hlbd_mf_group(UF_PUBLIC_ID,UF_SHOOT_ID,UF_NAME,UF_KIND,UF_TIMEZONE,UF_SENT_AT,UF_CLOSES_AT,UF_DIGITAL_SALES_UNTIL,UF_DELIVERY_DUE_AT,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT) VALUES('
            . $text($groupId) . ",{$shootNative},'Одинаковый класс','regular','Europe/Moscow',UTC_TIMESTAMP(),UTC_TIMESTAMP()+INTERVAL 7 DAY,UTC_TIMESTAMP()+INTERVAL 14 DAY,UTC_TIMESTAMP()+INTERVAL 14 DAY,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $groupNative = (int)$db->getInsertedId();
        $children = [];
        $frames = [];
        foreach (['A1', 'A2', 'B1', 'B2'] as $frameIndex => $code) {
            $image = imagecreatetruecolor(640, 480);
            if (!$image instanceof GdImage) {
                throw new RuntimeException('Cannot create fixture image.');
            }
            imagefill($image, 0, 0, imagecolorallocate($image, 20 + 25 * ($index * 4 + $frameIndex), 60, 90));
            $photoPath = '/runtime/digital-' . $index . '-' . $code . '.jpg';
            imagejpeg($image, $photoPath);
            imagedestroy($image);
            $photoId = $uuid();
            $inspected = new InspectedPhoto(
                tmpName: $photoPath,
                filename: str_starts_with($code, 'A') ? '0001.JPG' : $code . '.jpg',
                mimeType: 'image/jpeg',
                bytes: (int)filesize($photoPath),
                width: 640,
                height: 480,
                fingerprint: (string)hash_file('sha256', $photoPath),
            );
            $stored = $services->get(PrivatePhotoStorageInterface::class)->store($shootId, $inspected);
            $registered = $services->get(PhotoRepository::class)->register($photoId, $shootNative, $groupNative, $inspected, $stored);
            ($services->get(ProcessPhotoMessageHandler::class))(new ProcessPhotoMessage($registered->publicId, 1));
            $nativePhoto = (int)$db->query('SELECT ID FROM b_hlbd_mf_photo WHERE UF_PUBLIC_ID=' . $text($photoId))->fetchRaw()['ID'];
            $childCode = substr($code, 0, 1);
            $child = $services->get(MediaMutationRepository::class)->child($shootNative, $groupNative, $childCode);
            $services->get(MediaMutationRepository::class)->assign($child['id'], [$nativePhoto]);
            $assignment = $db->query('SELECT PUBLIC_ID FROM mf_photo_assignment WHERE CHILD_ID=' . (int)$child['id'] . ' AND PHOTO_ID=' . $nativePhoto)->fetchRaw();
            if (false === $assignment) {
                throw new RuntimeException('The fixture photo must have a real assignment.');
            }
            $children[$childCode] = $child;
            $frames[$code] = ['id' => $photoId, 'nativeId' => $nativePhoto, 'assignmentId' => (string)$assignment['PUBLIC_ID'], 'sha256' => $inspected->fingerprint];
            $original = $services->get(OriginalFilesInterface::class)->originals([$photoId])[$photoId];
            $state['originalPaths'][$photoId] = $original->absolutePath;
            $state[str_starts_with($code, 'A') ? 'expectedPhotos' : 'excludedPhotos'][$photoId] = $inspected->fingerprint;
        }
        $state['groups'][] = $groupId;
        foreach (0 === $index ? ['bundle', 'digital', 'gift', 'late', 'unpaid'] : ['bundle', 'digital'] as $kind) {
            $orderId = institutionPrintOrder($state, ['nativeId' => $groupNative, 'id' => $groupId], $clone, $uuid, $text);
            $state['orderIds'][] = $orderId;
            $orderNative = (int)$db->query('SELECT ID FROM mf_order WHERE PUBLIC_ID=' . $text($orderId))->fetchRaw()['ID'];
            $childCode = in_array($kind, ['gift', 'late', 'unpaid'], true) ? 'B' : 'A';
            $child = $children[$childCode];
            $childId = (int)$child['id'];
            $childPublicId = (string)$child['publicId'];
            $productKind = match ($kind) {
                'gift' => 'physical', 'digital' => 'digital', default => 'bundle'
            };
            $photo = 'bundle' === $productKind ? 'NULL' : $text($frames[$childCode . '1']['id']);
            $db->queryExecute("UPDATE mf_order_line SET CHILD_ID={$childId},CHILD_PUBLIC_ID=" . $text($childPublicId) . ',CHILD_CODE=' . $text($childCode)
                . ',ASSIGNMENT_PUBLIC_ID=' . $text($frames[$childCode . '1']['assignmentId'])
                . ',PRODUCT_KIND=' . $text($productKind) . ",PHOTO_PUBLIC_ID={$photo},PHOTO_CODE='fixture',QUANTITY=1,UNIT_PRICE=TOTAL,PRODUCT_PRICE=TOTAL,DISCOUNT=0,COVERED_BY_GIFT=0,STAFF_DISCOUNT=0 WHERE ORDER_ID={$orderNative}");
            if ('gift' === $kind) {
                $db->queryExecute("UPDATE mf_order SET GIFTS='[\"B\"]' WHERE ID={$orderNative}");
                $line = $db->query("SELECT * FROM mf_order_line WHERE ORDER_ID={$orderNative} ORDER BY LINE_NO DESC LIMIT 1")->fetchRaw();
                $clone('mf_order_line', $line, ['PUBLIC_ID' => $uuid(), 'LINE_NO' => (int)$line['LINE_NO'] + 1,
                    'PRODUCT_KIND' => 'digital', 'PHOTO_PUBLIC_ID' => $frames['B2']['id'], 'ASSIGNMENT_PUBLIC_ID' => $frames['B2']['assignmentId'],
                    'PRODUCT_PRICE' => 0, 'UNIT_PRICE' => 0, 'DISCOUNT' => 0, 'TOTAL' => 0, 'COVERED_BY_GIFT' => 1]);
            }
            $db->queryExecute("UPDATE mf_order SET ITEM_COUNT=(SELECT SUM(QUANTITY) FROM mf_order_line WHERE ORDER_ID={$orderNative}) WHERE ID={$orderNative}");
            if ('unpaid' === $kind) {
                $db->queryExecute("DELETE FROM mf_payment_fact WHERE ORDER_ID={$orderNative}");
                $db->queryExecute("DELETE FROM mf_payment_attempt WHERE ORDER_ID={$orderNative}");
                $db->queryExecute("UPDATE mf_order SET PAYMENT_STATUS='pending',PAID_AT=NULL,LATE_PAYMENT=0 WHERE ID={$orderNative}");
            } elseif ('late' === $kind) {
                foreach (['mf_order', 'mf_payment_attempt', 'mf_payment_fact'] as $table) {
                    $db->queryExecute("UPDATE {$table} SET LATE_PAYMENT=1 WHERE " . ('mf_order' === $table ? 'ID' : 'ORDER_ID') . "={$orderNative}");
                }
            } elseif ('bundle' === $kind && 0 === $index) {
                $created = gmdate('Y-m-d H:i:s', time() - 46 * 86400);
                $paid = gmdate('Y-m-d H:i:s', time() - 45 * 86400);
                $db->queryExecute('UPDATE mf_order SET CREATED_AT=' . $text($created) . ',PAID_AT=' . $text($paid) . " WHERE ID={$orderNative}");
                foreach (['mf_payment_attempt', 'mf_payment_fact'] as $table) {
                    $db->queryExecute("UPDATE {$table} SET PAID_AT=" . $text($paid) . ',CREATED_AT=' . $text($created) . " WHERE ORDER_ID={$orderNative}");
                }
                $keys = $services->get(OrderAccessKeys::class);
                $keys->revoke($orderNative, new DateTimeImmutable('now', new DateTimeZone('UTC')), 'replaced');
                $state['oldKey'] = $keys->issue($orderNative, new DateTimeImmutable($created, new DateTimeZone('UTC')), 'recovery')->key;
            }
        }
    }
    $invalid = $db->query('SELECT COUNT(*) AS TOTAL FROM mf_order_line l INNER JOIN mf_order o ON o.ID=l.ORDER_ID
        LEFT JOIN mf_photo_assignment a ON a.PUBLIC_ID=l.ASSIGNMENT_PUBLIC_ID AND a.CHILD_ID=l.CHILD_ID
        LEFT JOIN b_hlbd_mf_photo p ON p.ID=a.PHOTO_ID WHERE o.INSTITUTION_ID=' . $institutionNative
        . ' AND (a.PUBLIC_ID IS NULL OR (l.PHOTO_PUBLIC_ID IS NOT NULL AND l.PHOTO_PUBLIC_ID<>p.UF_PUBLIC_ID))')->fetchRaw();
    if (0 !== (int)$invalid['TOTAL']) {
        throw new RuntimeException('Fixture order lines must keep their own child-photo assignments.');
    }
    file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR));
}
if (null === $state) {
    throw new RuntimeException('Set up the digital fixture first.');
}
$answer = match ((string)$request['action']) {
    'setup' => array_intersect_key($state, array_flip(['institutionId', 'orderIds', 'oldKey', 'groups', 'expectedPhotos', 'excludedPhotos'])),
    'revoke', 'grant' => (static function() use ($db, $state, $request): array {
        $institution = (int)$state['institutionNativeId'];
        $actor = (int)$state['curatorId'];
        $db->queryExecute("DELETE FROM b_hlbd_mf_institution_assignment WHERE UF_INSTITUTION_ID={$institution} AND UF_ROLE='curator'");
        if ('grant' === $request['action']) {
            $db->queryExecute("INSERT INTO b_hlbd_mf_institution_assignment(UF_INSTITUTION_ID,UF_ROLE,UF_USER_ID,UF_CREATED_AT,UF_UPDATED_AT) VALUES({$institution},'curator',{$actor},UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        }

        return ['assigned' => 'grant' === $request['action']];
    })(),
    'hide', 'restore' => (static function() use ($state, $request): array {
        $original = reset($state['originalPaths']);
        $hidden = $original . '.digital-fixture-hidden';
        [$from, $to] = 'hide' === $request['action'] ? [$original, $hidden] : [$hidden, $original];
        if (!rename($from, $to)) {
            throw new RuntimeException('Cannot move the fixture original.');
        }

        return ['moved' => true];
    })(),
    default => throw new RuntimeException('Unknown digital fixture action.'),
};
file_put_contents('/runtime/digital-answer.json', json_encode($answer, JSON_THROW_ON_ERROR));
echo "Institution digital fixture complete\n";
