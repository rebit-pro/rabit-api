<?php

declare(strict_types=1);
use Bitrix\Main\Loader;

foreach (['rebit.share', 'morefoto.access', 'morefoto.organization', 'morefoto.media'] as $dependency) {
    if (!Loader::includeModule($dependency)) {
        throw new RuntimeException('Required module is unavailable: ' . $dependency);
    }
}
