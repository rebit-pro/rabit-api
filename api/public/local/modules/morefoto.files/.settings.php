<?php

declare(strict_types=1);

use Morefoto\Files\Presentation\Command\DispatchPendingDownloadsCommand;
use Morefoto\Files\Presentation\Command\FilesConsumerCommand;
use Morefoto\Files\Presentation\Command\PurgeDownloadsCommand;

return [
    'services' => ['value' => require __DIR__ . '/di/files.php', 'readonly' => true],
    'console' => ['value' => ['commands' => [FilesConsumerCommand::class, DispatchPendingDownloadsCommand::class, PurgeDownloadsCommand::class]], 'readonly' => true],
];
