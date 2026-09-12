<?php

declare(strict_types=1);

use Bitrix\Main\Loader;

foreach (['rebit.share', 'highloadblock', 'morefoto.access'] as $dependency) {
    if (!Loader::includeModule($dependency)) {
        throw new RuntimeException('Required module is unavailable: ' . $dependency);
    }
}
