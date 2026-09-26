<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Contract;

/**
 * Serializes work on one private original: the same content always lands on the same path, so an upload that is
 * between storing and registering the file and a deletion that checks whether the file is still used must not interleave.
 */
interface OriginalFileLockInterface
{
    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function synchronized(string $originalPath, callable $operation): mixed;
}
