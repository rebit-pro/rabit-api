<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Files\Application\Files\UseCase\ConsumeFilesUseCase;

$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
define('NO_AGENT_CHECK', true);
define('NO_AGENT_STATISTIC', true);
define('NO_KEEP_STATISTIC', true);
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';

if (!Loader::includeModule('morefoto.files')) {
    throw new RuntimeException('Cannot load isolated MoreFoto Files module.');
}
ServiceLocator::getInstance()->get(ConsumeFilesUseCase::class)->execute(
    (int)(getenv('FILES_E2E_LIMIT') ?: 100),
    (int)(getenv('FILES_E2E_TIME_LIMIT') ?: 900),
);
