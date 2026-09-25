<?php

declare(strict_types=1);
use Bitrix\Main\Loader;

// Support reads the gallery, group directory and staff scope only through public contracts of these modules.
foreach (['rebit.share', 'rebit.notification', 'morefoto.access', 'morefoto.organization', 'morefoto.media'] as $dependency) {
    if (!Loader::includeModule($dependency)) {
        throw new RuntimeException('Required module is unavailable: ' . $dependency);
    }
}
