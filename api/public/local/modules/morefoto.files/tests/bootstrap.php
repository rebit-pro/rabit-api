<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests;

$loader = require dirname(__DIR__, 5) . '/vendor/autoload.php';
$loader->addPsr4('Morefoto\Files\\', dirname(__DIR__) . '/lib');
$loader->addPsr4('Morefoto\Files\Tests\\', __DIR__);
