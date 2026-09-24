<?php

declare(strict_types=1);

return [
    'services' => [
        'value' => array_merge(
            require __DIR__ . '/di/auth.php',
            require __DIR__ . '/di/access.php',
        ),
        'readonly' => true,
    ],
];
