<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests;

$loader = require dirname(__DIR__, 5) . '/vendor/autoload.php';
$loader->addPsr4('Morefoto\Payment\\', dirname(__DIR__) . '/lib');
$loader->addPsr4('Morefoto\Payment\Tests\\', __DIR__);
