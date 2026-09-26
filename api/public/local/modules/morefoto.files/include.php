<?php

declare(strict_types=1);
use Bitrix\Main\Loader;

// Commerce provides the order file right, Media provides private originals.
foreach (['rebit.share', 'morefoto.commerce', 'morefoto.media'] as $dependency) {
    if (!Loader::includeModule($dependency)) {
        throw new RuntimeException('Required module is unavailable: ' . $dependency);
    }
}
