<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Cache;

interface CacheCleanerInterface
{
    public function cleanManaged(string $cacheId): void;

    public function cleanDir($initDir = false, $baseDir = 'cache'): void;

    /**
     * @param string[] $tags
     */
    public function cleanTagged(array $tags): void;
}
