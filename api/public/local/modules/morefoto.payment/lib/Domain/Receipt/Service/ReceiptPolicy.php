<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Receipt\Service;

/**
 * Сроки сверки кассового чека с провайдером: проверки учащённо сразу после события, затем раз в час; чек, не
 * зарегистрированный за 72 часа, требует ручной проверки (по правилам ЮKassa — обращения в поддержку). Повтор создания
 * закрывающего чека допустим только в срок гарантии ключа идемпотентности. Моменты — UTC `Y-m-d H:i:s`.
 */
final readonly class ReceiptPolicy
{
    private const array CHECK_DELAYS = [60, 120, 300, 900];
    private const int HOURLY_DELAY = 3600;
    private const int REGISTRATION_TIMEOUT = 259200;
    private const int PROVIDER_KEY_TTL = 86400;

    public function firstCheckAt(\DateTimeImmutable $now): string
    {
        return $this->utc($now->modify('+' . self::CHECK_DELAYS[0] . ' seconds'));
    }

    /** @param int $checkCount проверок вместе с текущей */
    public function nextCheckAt(int $checkCount, \DateTimeImmutable $now): string
    {
        return $this->utc($now->modify('+' . (self::CHECK_DELAYS[$checkCount] ?? self::HOURLY_DELAY) . ' seconds'));
    }

    public function expired(string $createdAt, \DateTimeImmutable $now): bool
    {
        return $this->utc($now->modify('-' . self::REGISTRATION_TIMEOUT . ' seconds')) >= $createdAt;
    }

    public function providerKeyUsable(string $createdAt, \DateTimeImmutable $now): bool
    {
        return $this->utc($now->modify('-' . self::PROVIDER_KEY_TTL . ' seconds')) < $createdAt;
    }

    public function utc(\DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
