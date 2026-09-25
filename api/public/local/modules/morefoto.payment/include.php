<?php

declare(strict_types=1);
use Bitrix\Main\Loader;

// Commerce provides the order payment contract; Access provides the staff scope.
foreach (['rebit.share', 'morefoto.access', 'morefoto.commerce'] as $dependency) {
    if (!Loader::includeModule($dependency)) {
        throw new RuntimeException('Required module is unavailable: ' . $dependency);
    }
}
