<?php

declare(strict_types=1);

use Rebit\Share\Application\Contract\Cache\CacheCleanerInterface;
use Rebit\Share\Infrastructure\Bitrix\Cache\BitrixCacheCleaner;

return [
    // Cache
    CacheCleanerInterface::class => [
        'className' => BitrixCacheCleaner::class,
    ],
];
