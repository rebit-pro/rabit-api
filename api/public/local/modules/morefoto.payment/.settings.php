<?php

declare(strict_types=1);

use Morefoto\Payment\Presentation\Command\ReconcilePaymentsCommand;

return [
    'services' => ['value' => require __DIR__ . '/di/payment.php', 'readonly' => true],
    'console' => ['value' => ['commands' => [ReconcilePaymentsCommand::class]], 'readonly' => true],
];
