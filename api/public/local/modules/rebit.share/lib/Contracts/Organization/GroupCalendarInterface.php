<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization;

use Rebit\Share\Contracts\Organization\Dto\CalendarCommandInputDto;
use Rebit\Share\Contracts\Organization\Dto\CalendarMutationOutputDto;

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
}
