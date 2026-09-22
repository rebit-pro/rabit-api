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

    /** Explicit extension only: later than existing close and server time, with reason, actor, revision and idempotency. */
    public function extend(CalendarCommandInputDto $input, \DateTimeImmutable $newClosesAt): CalendarMutationOutputDto;

    /**
     * Records the manual delivery moment reported by staff; never later than server time.
     * The first fact sets close +7 and delivery +7 calendar days; a repeated fact preserves every deadline.
     * Requires lock() and actor authorization; the caller owns idempotency and the operation ID.
     */
    public function recordLinkSent(LinkSentInputDto $input): CalendarMutationOutputDto;

    /**
     * Replaces an erroneous recorded delivery moment and recalculates the deadlines, keeping a later agreed extension.
     * Requires lock(), actor authorization and a reason; the caller owns idempotency and the operation ID.
     */
    public function correctLinkSent(LinkSentInputDto $input): CalendarMutationOutputDto;
}
