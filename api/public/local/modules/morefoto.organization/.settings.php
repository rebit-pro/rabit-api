<?php

declare(strict_types=1);

return ['services' => ['value' => array_merge(require __DIR__ . '/di/institution.php', require __DIR__ . '/di/http.php', require __DIR__ . '/di/structure.php', require __DIR__ . '/di/calendar.php'), 'readonly' => true]];
