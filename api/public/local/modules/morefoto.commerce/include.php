<?php

declare(strict_types=1);

use Bitrix\Main\Loader;

// Legal is reached only through the shared Consent contract and init.php loads it first. Requiring it here would stop
// the bootstrap of Version20260925230001, the migration that registers morefoto.legal (issue #117).
foreach (['rebit.share', 'morefoto.access', 'morefoto.organization', 'morefoto.media', 'morefoto.handoff'] as $dependency) {
    if (!Loader::includeModule($dependency)) {
        throw new RuntimeException('Required module is unavailable: ' . $dependency);
    }
}
