<?php

declare(strict_types=1);

use Rebit\Leadhunter\Presentation\Command\LeadHunt\ScanLeadsCommand;

return [
    'services' => [
        'value' => require __DIR__ . '/di/LeadHunt.php',
        'readonly' => true,
    ],
    'console' => [
        'value' => [
            'commands' => [
                ScanLeadsCommand::class,
            ],
        ],
        'readonly' => true,
    ],
];
