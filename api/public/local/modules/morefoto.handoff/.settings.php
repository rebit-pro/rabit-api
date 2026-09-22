<?php

declare(strict_types=1);

return ['services' => ['value' => array_merge(require __DIR__ . '/di/handoff.php', require __DIR__ . '/di/link.php'), 'readonly' => true]];
