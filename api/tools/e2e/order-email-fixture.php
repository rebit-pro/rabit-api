<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Files\Application\Delivery\UseCase\DispatchOrderEmailsUseCase;

// J2: the stand has no cron and no mail worker. On a request of the browser it runs app:files:order-emails once and returns
// the order key from the latest queued letter to the recipient, as the parent would read it in the mailbox.
if ('test' !== getenv('APP_ENV') || !is_file('/runtime/j2-request.json')) {
    throw new RuntimeException('The order email fixture requires the disposable stand and a request of the browser.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
if (!Loader::includeModule('morefoto.files')) {
    throw new RuntimeException('Cannot load isolated MoreFoto Files module.');
}
$request = json_decode((string)file_get_contents('/runtime/j2-request.json'), true, flags: JSON_THROW_ON_ERROR);
$result = ServiceLocator::getInstance()->get(DispatchOrderEmailsUseCase::class)->execute(100);
$connection = Application::getConnection();
$recipient = $connection->getSqlHelper()->forSql((string)$request['recipient']);
$letter = $connection->query("SELECT BODY FROM b_rebit_notification_operation WHERE CONSUMER_KEY='files-order-email' AND RECIPIENT='{$recipient}'
    ORDER BY CREATED_AT DESC,ID DESC LIMIT 1")->fetchRaw();
$key = is_array($letter) && 1 === preg_match('~/orders/access/([a-f0-9]{64})~', (string)$letter['BODY'], $match) ? $match[1] : null;
file_put_contents('/runtime/j2-answer.json', json_encode(['created' => $result->created, 'queued' => $result->queued, 'key' => $key], JSON_THROW_ON_ERROR));
echo "J2 order email fixture: dispatched\n";
