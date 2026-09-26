<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Media\Application\Gallery\Service\GalleryCapabilityLifecycle;
use Morefoto\Media\Application\Photo\Contract\PrivatePhotoStorageInterface;
use Morefoto\Media\Application\Photo\Dto\InspectedPhoto;
use Morefoto\Media\Application\Photo\Message\Handler\ProcessPhotoMessageHandler;
use Morefoto\Media\Application\Photo\Message\ProcessPhotoMessage;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Ramsey\Uuid\Uuid;

if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix')) {
    throw new RuntimeException('Storefront fixture requires the disposable test runtime.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    if (null !== $error->getPrevious()) {
        fwrite(STDERR, $error->getPrevious()->getMessage() . PHP_EOL);
    }
    exit(1);
});
foreach (['morefoto.organization', 'morefoto.media', 'morefoto.handoff', 'morefoto.commerce'] as $module) {
    if (!Loader::includeModule($module)) {
        throw new RuntimeException('Missing fixture module.');
    }
}
$connection = Application::getConnection();
$uuid = Uuid::uuid4()->toString();
$connection->queryExecute("INSERT INTO b_hlbd_mf_institution(UF_PUBLIC_ID,UF_NAME,UF_ADDRESS,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT)
    VALUES('{$uuid}','E4 Тестовый детский сад','Тестовый адрес',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$institution = (int)$connection->getInsertedId();
$shootUuid = Uuid::uuid4()->toString();
$connection->queryExecute("INSERT INTO b_hlbd_mf_shoot(UF_PUBLIC_ID,UF_INSTITUTION_ID,UF_NAME,UF_DATE,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT)
    VALUES('{$shootUuid}',{$institution},'E4 Осенняя съёмка',NULL,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$shoot = (int)$connection->getInsertedId();
$services = ServiceLocator::getInstance();
$lifecycle = $services->get(GalleryCapabilityLifecycle::class);
$mutations = $services->get(MediaMutationRepository::class);
$output = [];
foreach (['open', 'preparing', 'closed', 'revoked'] as $index => $state) {
    $groupUuid = Uuid::uuid4()->toString();
    $calendar = match ($state) {
        'preparing' => 'NULL,NULL,NULL',
        'closed' => 'UTC_TIMESTAMP()-INTERVAL 8 DAY,UTC_TIMESTAMP()-INTERVAL 1 DAY,UTC_TIMESTAMP()+INTERVAL 6 DAY',
        default => 'UTC_TIMESTAMP(),UTC_TIMESTAMP()+INTERVAL 7 DAY,UTC_TIMESTAMP()+INTERVAL 14 DAY',
    };
    $connection->queryExecute("INSERT INTO b_hlbd_mf_group(UF_PUBLIC_ID,UF_SHOOT_ID,UF_NAME,UF_KIND,UF_TIMEZONE,UF_SENT_AT,UF_CLOSES_AT,UF_DELIVERY_DUE_AT,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT)
        VALUES('{$groupUuid}',{$shoot},'E4 {$state}','regular','Europe/Moscow',{$calendar},1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    $group = (int)$connection->getInsertedId();
    $image = imagecreatetruecolor(640, 480);
    if (!$image instanceof GdImage) {
        throw new RuntimeException('Cannot create fixture image.');
    }
    imagefill($image, 0, 0, imagecolorallocate($image, 80 + 30 * $index, 140, 170));
    imagestring($image, 5, 180, 220, 'MoreFoto E4 test photo', imagecolorallocate($image, 255, 255, 255));
    $path = '/runtime/e4-' . $index . '.jpg';
    imagejpeg($image, $path);
    imagedestroy($image);
    $photoId = Uuid::uuid4()->toString();
    $photo = new InspectedPhoto($path, 'e4.jpg', 'image/jpeg', (int)filesize($path), 640, 480, (string)hash_file('sha256', $path));
    $storage = $services->get(PrivatePhotoStorageInterface::class);
    $registration = $services->get(PhotoRepository::class)->register($photoId, $shoot, $group, $photo, $storage->store($shootUuid, $photo));
    ($services->get(ProcessPhotoMessageHandler::class))(new ProcessPhotoMessage($registration->publicId, 1));
    $row = $connection->query("SELECT ID FROM b_hlbd_mf_photo WHERE UF_PUBLIC_ID='{$photoId}'")->fetch();
    if (false === $row) {
        throw new RuntimeException('Fixture photo missing.');
    }
    foreach (['A', 'B'] as $code) {
        $child = $mutations->child($shoot, $group, $code);
        $mutations->assign($child['id'], [(int)$row['ID']]);
    }
    $key = $lifecycle->issue($groupUuid);
    if ('revoked' === $state) {
        $lifecycle->revoke($key->token, $key->revision);
    }
    // J1: the browser compares downloaded originals with this digest.
    $output[$state] = ['token' => $key->token, 'groupId' => $groupUuid, 'photoId' => $photoId, 'sha256' => $photo->fingerprint];
}
file_put_contents('/runtime/e4-fixture.json', json_encode($output, JSON_THROW_ON_ERROR));
echo "E4 real media and capability fixture prepared.\n";
