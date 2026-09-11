<?php

declare(strict_types=1);

// The module uses native Bitrix PSR-4 in production; standalone PHPUnit has no Loader.
$loader = require dirname(__DIR__, 5) . '/vendor/autoload.php';
$loader->addPsr4('Morefoto\Organization\\', dirname(__DIR__) . '/lib/', true);
