<?php

declare(strict_types=1);
use Bitrix\Main\Loader;

// Commerce provides the order file right and the order email, Media private originals, Notification the letters (J2).
foreach (['rebit.share', 'rebit.notification', 'morefoto.commerce', 'morefoto.media'] as $dependency) {
    if (!Loader::includeModule($dependency)) {
        throw new RuntimeException('Required module is unavailable: ' . $dependency);
    }
}
