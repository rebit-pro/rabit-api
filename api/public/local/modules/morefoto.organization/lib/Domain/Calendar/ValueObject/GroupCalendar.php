<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Calendar\ValueObject;

use Morefoto\Organization\Domain\Calendar\Exception\CalendarRuleViolation;
use Morefoto\Organization\Domain\Calendar\Service\CalendarPolicy;

final readonly class GroupCalendar
{
    public function __construct(
        public ?\DateTimeImmutable $sentAt = null,
        public ?\DateTimeImmutable $closesAt = null,
        public ?\DateTimeImmutable $deliveryDueAt = null,
        public string $timezone = CalendarPolicy::TIMEZONE,
    ) {
        if (CalendarPolicy::TIMEZONE !== $timezone) {
            throw new \InvalidArgumentException('Unsupported group calendar timezone.');
        }
        if ((null === $sentAt) !== (null === $closesAt) || (null === $sentAt) !== (null === $deliveryDueAt)) {
            throw new \InvalidArgumentException('Group calendar dates must be either all set or all absent.');
        }
        foreach ([$sentAt, $closesAt, $deliveryDueAt] as $instant) {
            if (null !== $instant) {
                $year = (int)$instant->setTimezone(new \DateTimeZone('UTC'))->format('Y');
                if (1000 > $year || 9999 < $year || '000000' !== $instant->format('u')) {
                    throw new \InvalidArgumentException('Calendar timestamps must fit UTC DATETIME with second precision.');
                }
            }
        }
        if (null !== $sentAt && ($sentAt >= $closesAt || $closesAt >= $deliveryDueAt)) {
            throw new \InvalidArgumentException('Group calendar dates must be strictly ordered.');
        }
    }

    public static function fromStorage(?string $sentAt, ?string $closesAt, ?string $deliveryDueAt, string $timezone = CalendarPolicy::TIMEZONE): self
    {
        return new self(self::storedInstant($sentAt), self::storedInstant($closesAt), self::storedInstant($deliveryDueAt), $timezone);
    }

    public function confirmLinkSent(\DateTimeImmutable $now): self
    {
        if (null !== $this->sentAt) {
            return $this;
        }
        $close = CalendarPolicy::addDays($now, 7, $this->timezone);

        return new self($now, $close, CalendarPolicy::addDays($close, 7, $this->timezone), $this->timezone);
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
        $close = CalendarPolicy::addDays($sentAt, 7, $this->timezone);

        return new self($sentAt, $close, CalendarPolicy::addDays($close, 7, $this->timezone), $this->timezone);
    }

    /** An agreed extension beyond the regular seven days survives the correction when it is still later. */
    public function correctLinkSent(\DateTimeImmutable $sentAt, \DateTimeImmutable $now): self
    {
        if (null === $this->sentAt || null === $this->closesAt) {
            throw new CalendarRuleViolation('LINK_NOT_SENT');
        }
        if ($sentAt > $now) {
            throw new CalendarRuleViolation('SENT_AT_IN_FUTURE');
        }
        if ($sentAt->getTimestamp() === $this->sentAt->getTimestamp()) {
            throw new CalendarRuleViolation('SENT_AT_UNCHANGED');
        }
        $close = CalendarPolicy::addDays($sentAt, 7, $this->timezone);
        $extended = $this->closesAt > CalendarPolicy::addDays($this->sentAt, 7, $this->timezone);
        if ($extended && $this->closesAt > $close) {
            $close = $this->closesAt;
        }

        return new self($sentAt, $close, CalendarPolicy::addDays($close, 7, $this->timezone), $this->timezone);
    }

    public function extend(\DateTimeImmutable $newClose, \DateTimeImmutable $now): self
    {
        if (null === $this->sentAt || null === $this->closesAt) {
            throw new \DomainException('A group without confirmed link delivery cannot be extended.');
        }
        if ($newClose <= $this->closesAt || $newClose <= $now) {
            throw new \DomainException('The new deadline must be later than both the current deadline and server time.');
        }

        return new self($this->sentAt, $newClose, CalendarPolicy::addDays($newClose, 7, $this->timezone), $this->timezone);
    }

    public function status(\DateTimeImmutable $now): string
    {
        return match (true) {
            null === $this->sentAt => 'preparing',
            $now >= $this->closesAt => 'closed',
            default => 'open',
        };
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
