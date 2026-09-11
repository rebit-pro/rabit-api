<?php

declare(strict_types=1);

// The exception logger can run before init.php loads Composer and rebit.share.
require_once __DIR__ . '/../modules/rebit.share/lib/Infrastructure/Logger/LogSanitizer.php';
require_once __DIR__ . '/../modules/rebit.share/lib/Infrastructure/Logger/RequestIdGenerator.php';
require_once __DIR__ . '/../modules/rebit.share/lib/Infrastructure/Logger/SafeExceptionHandlerLog.php';
