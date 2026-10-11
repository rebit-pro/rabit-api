<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Calendar\ValueObject;

use Morefoto\Organization\Domain\Calendar\Exception\CalendarRuleViolation;
use Morefoto\Organization\Domain\Calendar\Service\CalendarPolicy;

/**
 * Calendar of one group from the single moment T0 when its link was sent: print sales close at `closesAt` (T0+7),
 * digital sales at `digitalSalesUntil` (T0+14, never earlier than print), physical delivery is due at `deliveryDueAt`.
 */
final readonly class GroupCalendar
{
    public const int PRINT_SALES_DAYS = 7;
    public const int DIGITAL_SALES_DAYS = 14;
    public const int DELIVERY_DAYS = 7;

    public function __construct(
        public ?\DateTimeImmutable $sentAt = null,
        public ?\DateTimeImmutable $closesAt = null,
        public ?\DateTimeImmutable $digitalSalesUntil = null,
        public ?\DateTimeImmutable $deliveryDueAt = null,
        public string $timezone = CalendarPolicy::TIMEZONE,
    ) {
        if (CalendarPolicy::TIMEZONE !== $timezone) {
            throw new \InvalidArgumentException('Unsupported group calendar timezone.');
        }
        $absent = null === $sentAt;
        if ($absent !== (null === $closesAt) || $absent !== (null === $digitalSalesUntil) || $absent !== (null === $deliveryDueAt)) {
            throw new \InvalidArgumentException('Group calendar dates must be either all set or all absent.');
        }
        foreach ([$sentAt, $closesAt, $digitalSalesUntil, $deliveryDueAt] as $instant) {
            if (null !== $instant) {
                $year = (int)$instant->setTimezone(new \DateTimeZone('UTC'))->format('Y');
                if (1000 > $year || 9999 < $year || '000000' !== $instant->format('u')) {
                    throw new \InvalidArgumentException('Calendar timestamps must fit UTC DATETIME with second precision.');
                }
            }
        }
        if (!$absent && ($sentAt >= $closesAt || $closesAt > $digitalSalesUntil || $closesAt >= $deliveryDueAt)) {
            throw new \InvalidArgumentException('Group calendar dates must be strictly ordered.');
        }
    }

    public static function fromStorage(?string $sentAt, ?string $closesAt, ?string $digitalSalesUntil, ?string $deliveryDueAt, string $timezone = CalendarPolicy::TIMEZONE): self
    {
        return new self(self::storedInstant($sentAt), self::storedInstant($closesAt), self::storedInstant($digitalSalesUntil), self::storedInstant($deliveryDueAt), $timezone);
    }

    public function confirmLinkSent(\DateTimeImmutable $now): self
    {
        if (null !== $this->sentAt) {
            return $this;
        }

        return $this->fromSent($now, CalendarPolicy::addDays($now, self::PRINT_SALES_DAYS, $this->timezone));
    }

    /** Staff report the actual moment of the manual delivery; a repeated report never moves the deadlines. */
    public function recordLinkSent(\DateTimeImmutable $sentAt, \DateTimeImmutable $now): self
    {
        if (null !== $this->sentAt) {
            return $this;
        }
        if ($sentAt > $now) {
            throw new CalendarRuleViolation('SENT_AT_IN_FUTURE');
        }

        return $this->fromSent($sentAt, CalendarPolicy::addDays($sentAt, self::PRINT_SALES_DAYS, $this->timezone));
    }

    /**
     * An agreed extension beyond the regular seven days survives the correction when it is still later. Once print
     * sales have closed the correction is refused: it must not reopen print after its composition was fixed.
     */
    public function correctLinkSent(\DateTimeImmutable $sentAt, \DateTimeImmutable $now): self
    {
        if (null === $this->sentAt || null === $this->closesAt) {
            throw new CalendarRuleViolation('LINK_NOT_SENT');
        }
        if ($now >= $this->closesAt) {
            throw new CalendarRuleViolation('PRINT_SALES_CLOSED');
        }
        if ($sentAt > $now) {
            throw new CalendarRuleViolation('SENT_AT_IN_FUTURE');
        }
        if ($sentAt->getTimestamp() === $this->sentAt->getTimestamp()) {
            throw new CalendarRuleViolation('SENT_AT_UNCHANGED');
        }
        $close = CalendarPolicy::addDays($sentAt, self::PRINT_SALES_DAYS, $this->timezone);
        $extended = $this->closesAt > CalendarPolicy::addDays($this->sentAt, self::PRINT_SALES_DAYS, $this->timezone);
        if ($extended && $this->closesAt > $close) {
            $close = $this->closesAt;
        }

        return $this->fromSent($sentAt, $close);
    }

    /** Extends print sales of an open group; digital sales never close before print. */
    public function extend(\DateTimeImmutable $newClose, \DateTimeImmutable $now): self
    {
        if (null === $this->sentAt || null === $this->closesAt || null === $this->digitalSalesUntil) {
            throw new \DomainException('A group without confirmed link delivery cannot be extended.');
        }
        if ($now >= $this->closesAt) {
            throw new CalendarRuleViolation('PRINT_SALES_CLOSED');
        }
        if ($newClose <= $this->closesAt || $newClose <= $now) {
            throw new \DomainException('The new deadline must be later than both the current deadline and server time.');
        }

        return new self(
            $this->sentAt,
            $newClose,
            max($this->digitalSalesUntil, $newClose),
            CalendarPolicy::addDays($newClose, self::DELIVERY_DAYS, $this->timezone),
            $this->timezone,
        );
    }

    /** preparing → open (print and digital) → digital (digital only) → closed; a deadline closes at its exact second. */
    public function status(\DateTimeImmutable $now): string
    {
        return match (true) {
            null === $this->sentAt => 'preparing',
            $now >= $this->digitalSalesUntil => 'closed',
            $now >= $this->closesAt => 'digital',
            default => 'open',
        };
    }

    private function fromSent(\DateTimeImmutable $sentAt, \DateTimeImmutable $close): self
    {
        return new self(
            $sentAt,
            $close,
            max(CalendarPolicy::addDays($sentAt, self::DIGITAL_SALES_DAYS, $this->timezone), $close),
            CalendarPolicy::addDays($close, self::DELIVERY_DAYS, $this->timezone),
            $this->timezone,
        );
    }

    private static function storedInstant(?string $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        if (false === $date || $date->format('Y-m-d H:i:s') !== $value) {
            throw new \InvalidArgumentException('Invalid persisted UTC calendar timestamp.');
        }

        return $date;
    }
}
