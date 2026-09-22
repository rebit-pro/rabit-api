<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media;

use Rebit\Share\Contracts\Media\Dto\GalleryLinkOutputDto;

/** Staff-visible gallery link of a group; buyers keep resolving it through GalleryAccessInterface. */
interface GalleryLinkInterface
{
    /** Newest active link whose raw key is stored, or null. No side effects. */
    public function current(string $groupId): ?GalleryLinkOutputDto;

    /** Inside the caller's transaction: returns the current link or issues a new one. The caller verified readiness. */
    public function ensure(string $groupId): GalleryLinkOutputDto;
}
