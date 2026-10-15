<?php

declare(strict_types=1);
use Bitrix\Main\Loader;

// Organization gives groups and print deadlines, Commerce paid print, Media originals, Access the staff scope.
foreach (['rebit.share', 'morefoto.access', 'morefoto.organization', 'morefoto.commerce', 'morefoto.media'] as $dependency) {
    if (!Loader::includeModule($dependency)) {
        throw new RuntimeException('Required module is unavailable: ' . $dependency);
    }
}
