<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Link\Enum;

enum LinkProblemEnum: string
{
    case NO_PHOTOS = 'noPhotos';
    case PHOTOS_PROCESSING = 'photosProcessing';
    case UNASSIGNED_PHOTOS = 'unassignedPhotos';
    case NO_PRODUCTS = 'noProducts';
    case STAFF_REQUESTS_PENDING = 'staffRequestsPending';
}
