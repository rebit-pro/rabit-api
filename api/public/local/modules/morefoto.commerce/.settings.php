<?php

declare(strict_types=1);

return [
    'services' => [
        'value' => array_merge(require __DIR__ . '/di/catalog.php', require __DIR__ . '/di/catalog-api.php'),
        'readonly' => true,
    ],
];
