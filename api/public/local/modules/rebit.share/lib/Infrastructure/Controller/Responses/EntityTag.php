<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Responses;

/** Entity tag of a versioned resource and the If-None-Match check (weak comparison, RFC 9110 13.1.2). */
final readonly class EntityTag
{
    public static function quote(string $value): string
    {
        return '"' . $value . '"';
    }

    public static function matches(?string $ifNoneMatch, string $etag): bool
    {
        if (null === $ifNoneMatch) {
            return false;
        }
        $opaque = str_starts_with($etag, 'W/') ? substr($etag, 2) : $etag;
        foreach (explode(',', $ifNoneMatch) as $candidate) {
            $candidate = trim($candidate);
            if ('*' === $candidate || $opaque === (str_starts_with($candidate, 'W/') ? substr($candidate, 2) : $candidate)) {
                return true;
            }
        }

        return false;
    }
}
