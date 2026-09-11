<?php

declare(strict_types=1);

return [
    'services' => [
        'value' => array_merge(
            require __DIR__ . '/di/auth.php',
        ),
        'readonly' => true,
    ],
];
