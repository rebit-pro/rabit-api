<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Handoff\Application\Link\UseCase\RunGalleryOpeningsUseCase;
use Morefoto\Handoff\Presentation\Command\RunGalleryOpeningsCommand;

if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix')) {
    throw new RuntimeException('Gallery opening verification requires the disposable test runtime.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL . $error->getTraceAsString() . PHP_EOL);
    exit(1);
});
foreach (['morefoto.organization', 'morefoto.media', 'morefoto.handoff'] as $module) {
    if (!Loader::includeModule($module)) {
        throw new RuntimeException('Missing gallery opening verification module.');
    }
}
$handoffSettings = require '/app/public/local/modules/morefoto.handoff/.settings.php';
if (!in_array(RunGalleryOpeningsCommand::class, $handoffSettings['console']['value']['commands'] ?? [], true)) {
    throw new RuntimeException('Scheduled opening command is not registered in Handoff console settings.');
}
$fixture = json_decode((string)file_get_contents('/runtime/i232-opening.json'), true, 512, JSON_THROW_ON_ERROR);
$groupId = (string)($fixture['groupId'] ?? '');
if (1 !== preg_match('/^[0-9a-f-]{36}$/D', $groupId)) {
    throw new RuntimeException('Invalid browser gallery opening fixture.');
}
$connection = Application::getConnection();
$read = static function() use ($connection, $groupId): array {
    $row = $connection->query("SELECT DATE_FORMAT(o.OPEN_AT,'%Y-%m-%d %H:%i:%s') AS OPEN_AT,o.STATUS,
        DATE_FORMAT(o.OPENED_AT,'%Y-%m-%d %H:%i:%s') AS OPENED_AT,
        DATE_FORMAT(o.MANUAL_SENT_AT,'%Y-%m-%d %H:%i:%s') AS MANUAL_SENT_AT,
        DATE_FORMAT(g.UF_SENT_AT,'%Y-%m-%d %H:%i:%s') AS SENT_AT,
        DATE_FORMAT(g.UF_CLOSES_AT,'%Y-%m-%d %H:%i:%s') AS CLOSES_AT,
        DATE_FORMAT(g.UF_DIGITAL_SALES_UNTIL,'%Y-%m-%d %H:%i:%s') AS DIGITAL_UNTIL,
        DATE_FORMAT(g.UF_DELIVERY_DUE_AT,'%Y-%m-%d %H:%i:%s') AS DELIVERY_DUE
        FROM mf_gallery_opening o JOIN b_hlbd_mf_group g ON g.ID=o.GROUP_ID WHERE g.UF_PUBLIC_ID='{$groupId}'")->fetch();
    if (false === $row) {
        throw new RuntimeException('Browser gallery opening fixture was not persisted.');
    }

    return $row;
};
$before = $read();
if ('scheduled' !== $before['STATUS'] || null !== $before['SENT_AT'] || null === $before['MANUAL_SENT_AT']) {
    throw new RuntimeException('Manual delivery must be recorded while calendar T0 remains absent.');
}
$zone = new DateTimeZone('UTC');
$openAt = new DateTimeImmutable((string)$before['OPEN_AT'], $zone);
$wait = $openAt->getTimestamp() - time() + 1;
if (0 < $wait) {
    if (20 < $wait) {
        throw new RuntimeException('Browser scheduled opening is unexpectedly far in the future.');
    }
    sleep($wait);
}
$worker = ServiceLocator::getInstance()->get(RunGalleryOpeningsUseCase::class);
$first = $worker->execute(100);
if (1 !== $first->opened) {
    throw new RuntimeException('Due gallery did not open.');
}
$after = $read();
$sent = new DateTimeImmutable((string)$after['SENT_AT'], $zone);
if ('opened' !== $after['STATUS'] || $after['SENT_AT'] !== $after['OPENED_AT'] || $sent < $openAt
    || $sent->getTimestamp() < time() - 10
    || $after['MANUAL_SENT_AT'] !== $before['MANUAL_SENT_AT']
    || $after['CLOSES_AT'] !== $sent->modify('+7 days')->format('Y-m-d H:i:s')
    || $after['DIGITAL_UNTIL'] !== $sent->modify('+14 days')->format('Y-m-d H:i:s')
    || $after['DELIVERY_DUE'] !== $sent->modify('+14 days')->format('Y-m-d H:i:s')) {
    throw new RuntimeException('Automatic opening did not preserve T0, manual delivery and +7/+14/+7 deadlines.');
}
$second = $worker->execute(100);
if (0 !== $second->opened || $read() !== $after) {
    throw new RuntimeException('Repeated worker changed the opened gallery.');
}
echo '#232 gallery opening integration passed' . PHP_EOL;
