<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/include/runtime-env.php';

use Bitrix\Main\Loader;

if (file_exists(__DIR__ . '/include/dev.php')) {
    require_once __DIR__ . '/include/dev.php';
}

Loader::includeModule('rebit.share');
Loader::includeModule('rebit.auth');
Loader::includeModule('morefoto.organization');
Loader::includeModule('rebit.notification');

Loader::includeModule('morefoto.access');
