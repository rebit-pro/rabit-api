<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Delivery\Service;

/**
 * Правила писем со ссылкой на заказ (J2): повтор со страницы заказа не чаще раза в 10 минут и не больше пяти в сутки
 * (J2-DEC-07), чтобы кнопкой нельзя было засыпать чужой ящик; H1 повторяет отправку до десяти раз (≈5 ч недоступного SMTP).
 */
final readonly class DeliveryPolicy
{
    public const int MAX_ATTEMPTS = 10;
    public const int DAILY_LIMIT = 5;
    private const string RESEND_INTERVAL = 'PT10M';
    private const string DAY = 'P1D';
    /** Подтверждение оплаты фиксируется в транзакции раньше её commit: курсор перечитывает последние минуты. */
    private const string CURSOR_OVERLAP = 'PT10M';

    public function resendAvailableAt(\DateTimeImmutable $lastCreatedAt): \DateTimeImmutable
    {
        return $lastCreatedAt->add(new \DateInterval(self::RESEND_INTERVAL));
    }

    public function dayStart(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->sub(new \DateInterval(self::DAY));
    }

    public function scanFrom(\DateTimeImmutable $cursor): \DateTimeImmutable
    {
        return $cursor->sub(new \DateInterval(self::CURSOR_OVERLAP));
    }
}
