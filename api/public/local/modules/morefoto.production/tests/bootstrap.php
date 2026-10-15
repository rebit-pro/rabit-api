<?php

declare(strict_types=1);

namespace Morefoto\Production\Tests;

$loader = require dirname(__DIR__, 5) . '/vendor/autoload.php';
$loader->addPsr4('Morefoto\Production\\', dirname(__DIR__) . '/lib');
$loader->addPsr4('Morefoto\Production\Tests\\', __DIR__);
