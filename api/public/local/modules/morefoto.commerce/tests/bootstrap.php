<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests;

$loader = require dirname(__DIR__, 5) . '/vendor/autoload.php';
$loader->addPsr4('Morefoto\Commerce\\', dirname(__DIR__) . '/lib');
require_once __DIR__ . '/bitrix-result.php';
