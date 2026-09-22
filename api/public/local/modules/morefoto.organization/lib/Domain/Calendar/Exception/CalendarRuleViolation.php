<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Calendar\Exception;

/** The message is a stable machine-readable rule code such as SENT_AT_IN_FUTURE. */
final class CalendarRuleViolation extends \DomainException {}
