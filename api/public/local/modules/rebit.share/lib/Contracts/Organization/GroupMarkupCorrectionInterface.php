<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization;

use Rebit\Share\Contracts\Organization\Dto\MarkupCorrectionInputDto;
use Rebit\Share\Contracts\Organization\Dto\MarkupCorrectionOutputDto;

interface GroupMarkupCorrectionInterface
{
    /**
     * Pauses the sales of a transmitted group that still sells, so its markup can be corrected (D4). Advances the group
     * revision: stored quotes become stale. Refused with LINK_NOT_SENT before transmission and GALLERY_CLOSED after all
     * sales; a repeated start keeps the original moment. Requires GroupCalendarInterface::lock() and actor authorization;
     * the caller checks that the group has no orders and owns idempotency.
     */
    public function start(MarkupCorrectionInputDto $input): MarkupCorrectionOutputDto;

    /**
     * Resumes the sales after a correction and advances the group revision; T0 and the deadlines stay as they were.
     * Refused with CORRECTION_NOT_STARTED. Requires lock(), actor authorization and the caller's readiness check.
     */
    public function finish(MarkupCorrectionInputDto $input): MarkupCorrectionOutputDto;
}
