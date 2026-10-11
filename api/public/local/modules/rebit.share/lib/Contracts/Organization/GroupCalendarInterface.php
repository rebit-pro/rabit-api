<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization;

use Rebit\Share\Contracts\Organization\Dto\CalendarCommandInputDto;
use Rebit\Share\Contracts\Organization\Dto\CalendarMutationOutputDto;
use Rebit\Share\Contracts\Organization\Dto\LinkSentInputDto;

interface GroupCalendarInterface
{
    /**
     * Caller owns one local transaction and locks AccessState first.
     * Locks Institution -> Shoot -> Group, returning the native institution ID for scope validation.
     * Caller then locks actor profiles/Auth and verifies permission before either mutation.
     * This provider never begins, commits or rolls back the caller's transaction.
     */
    public function lock(string $groupId): int;

    /** Internal projection; caller is responsible for authorization. No database Result crosses this boundary. */
    public function get(string $groupId): CalendarMutationOutputDto;

    /** First confirmed delivery uses server time. Retransmission preserves every deadline. Requires lock() and actor authorization. */
    public function confirmLinkSent(CalendarCommandInputDto $input): CalendarMutationOutputDto;

    /**
     * Explicit print extension of an open group only: later than existing close and server time, with reason, actor,
     * revision and idempotency. Digital sales never close before print.
     */
    public function extend(CalendarCommandInputDto $input, \DateTimeImmutable $newClosesAt): CalendarMutationOutputDto;

    /**
     * Records the manual delivery moment reported by staff; never later than server time.
     * The first fact sets print close +7, digital close +14 and delivery print close +7 calendar days; a repeated fact
     * preserves every deadline.
     * Requires lock() and actor authorization; the caller owns idempotency and the operation ID.
     */
    public function recordLinkSent(LinkSentInputDto $input): CalendarMutationOutputDto;

    /**
     * Replaces an erroneous recorded delivery moment and recalculates the deadlines, keeping a later agreed extension.
     * Refused with PRINT_SALES_CLOSED once print sales have closed.
     * Requires lock(), actor authorization and a reason; the caller owns idempotency and the operation ID.
     */
    public function correctLinkSent(LinkSentInputDto $input): CalendarMutationOutputDto;
}
