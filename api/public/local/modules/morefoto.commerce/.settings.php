<?php

declare(strict_types=1);

return [
    'services' => [
        'value' => require __DIR__ . '/di/catalog.php',
        'readonly' => true,
    ],
];
