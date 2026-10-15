<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Production\Application\Archive\UseCase\RunProductionUseCase;

// L1: the stand has no cron. On a request of the browser it either closes print sales of the J4 print group right after
// its last paid order (paid before the deadline, so the order stays in print) or runs app:production:run once.
if ('test' !== getenv('APP_ENV') || !is_file('/runtime/l1-request.json')) {
    throw new RuntimeException('The production fixture requires the disposable stand and a request of the browser.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
if (!Loader::includeModule('morefoto.production')) {
    throw new RuntimeException('Cannot load isolated MoreFoto Production module.');
}
$request = json_decode((string)file_get_contents('/runtime/l1-request.json'), true, flags: JSON_THROW_ON_ERROR);
$connection = Application::getConnection();
$answer = match ((string)$request['action']) {
    'close' => (static function() use ($connection, $request): array {
        $group = $connection->getSqlHelper()->forSql((string)$request['groupId']);
        // E7 keeps sentAt + 7 days = print close, + 14 days = digital close, delivery a week after print.
        $connection->queryExecute("UPDATE b_hlbd_mf_group g INNER JOIN (SELECT MAX(PAID_AT)+INTERVAL 1 SECOND AS CLOSES_AT FROM mf_order
            WHERE GROUP_PUBLIC_ID='{$group}' AND PAYMENT_STATUS='paid') o ON o.CLOSES_AT IS NOT NULL
            SET g.UF_SENT_AT=o.CLOSES_AT-INTERVAL 7 DAY,g.UF_CLOSES_AT=o.CLOSES_AT,g.UF_DIGITAL_SALES_UNTIL=o.CLOSES_AT+INTERVAL 7 DAY,
                g.UF_DELIVERY_DUE_AT=o.CLOSES_AT+INTERVAL 7 DAY,g.UF_REVISION=g.UF_REVISION+1,g.UF_UPDATED_AT=UTC_TIMESTAMP()
            WHERE g.UF_PUBLIC_ID='{$group}'");
        if (1 !== $connection->getAffectedRowsCount()) {
            throw new RuntimeException('The print group has no paid order to close after.');
        }
        // The deadline lies in the past but after the L1 release of this stand: the scheduler takes it an hour later.
        sleep(1);

        return ['closed' => true];
    })(),
    'run' => (static function(): array {
        $result = ServiceLocator::getInstance()->get(RunProductionUseCase::class)->execute(20);

        return ['scheduled' => $result->scheduled, 'built' => $result->built, 'failed' => $result->failed, 'expired' => $result->expired];
    })(),
    default => throw new RuntimeException('Unknown production fixture action.'),
};
file_put_contents('/runtime/l1-answer.json', json_encode($answer, JSON_THROW_ON_ERROR));
echo "L1 production fixture: {$request['action']}\n";
