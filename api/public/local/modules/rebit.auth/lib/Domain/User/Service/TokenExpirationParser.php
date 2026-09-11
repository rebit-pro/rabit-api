<?php

declare(strict_types=1);

namespace Rebit\Auth\Domain\User\Service;

use Bitrix\Main\Type\DateTime;

final readonly class TokenExpirationParser
{
    public static function parse(mixed $value): ?DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }
        if (!is_string($value) || '' === $value || str_contains($value, "\0")) {
            return null;
        }

        // New writes use UTC; legacy strings use the original server timezone.
        foreach (['Y-m-d\TH:i:s\Z', 'Y-m-d H:i:s', 'd.m.Y H:i:s'] as $format) {
            $timezone = str_ends_with($format, '\Z') ? new \DateTimeZone('UTC') : null;
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value, $timezone);
            $errors = \DateTimeImmutable::getLastErrors();
            if (false !== $date && false === $errors && $value === $date->format($format)) {
                return DateTime::createFromTimestamp($date->getTimestamp());
            }
        }

        return null;
    }

    public static function format(DateTime $value): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $value->getTimestamp());
    }
}
