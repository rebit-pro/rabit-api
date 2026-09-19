<?php

declare(strict_types=1);

$loader = require dirname(__DIR__, 5) . '/vendor/autoload.php';
$loader->addPsr4('Morefoto\Media\\', dirname(__DIR__) . '/lib/', true);
