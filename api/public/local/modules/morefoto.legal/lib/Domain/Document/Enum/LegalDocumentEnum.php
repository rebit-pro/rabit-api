<?php

declare(strict_types=1);

namespace Morefoto\Legal\Domain\Document\Enum;

enum LegalDocumentEnum: string
{
    case PRIVACY = 'privacy';
    case OFFER = 'offer';
    case BUYER_CONSENT = 'buyer-consent';
    case STAFF_CONSENT = 'staff-consent';
}
