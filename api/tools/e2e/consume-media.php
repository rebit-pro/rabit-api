<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Media\Application\Photo\UseCase\ConsumeMediaUseCase;

$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
define('NO_AGENT_CHECK', true);
define('NO_AGENT_STATISTIC', true);
define('NO_KEEP_STATISTIC', true);
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';

if (!Loader::includeModule('morefoto.media')) {
    throw new RuntimeException('Cannot load isolated MoreFoto Media module.');
}
ServiceLocator::getInstance()->get(ConsumeMediaUseCase::class)->execute(
    (int)(getenv('MEDIA_E2E_LIMIT') ?: 100),
    (int)(getenv('MEDIA_E2E_TIME_LIMIT') ?: 900),
);
