<?php

declare(strict_types=1);
$loader = require dirname(__DIR__, 5) . '/vendor/autoload.php';
$loader->addPsr4('Morefoto\Legal\\', dirname(__DIR__) . '/lib/', true);
$loader->addPsr4('Morefoto\Legal\Tests\\', __DIR__ . '/', true);
