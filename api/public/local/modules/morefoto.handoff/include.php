<?php

declare(strict_types=1);
use Bitrix\Main\Loader;

// Commerce includes Handoff; link readiness resolves the Commerce contract lazily after init.php loaded both modules.
foreach (['rebit.share', 'morefoto.access', 'morefoto.organization', 'morefoto.media'] as $dependency) {
    if (!Loader::includeModule($dependency)) {
        throw new RuntimeException('Required module is unavailable: ' . $dependency);
    }
}
