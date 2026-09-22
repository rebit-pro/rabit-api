<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\Service;

use Morefoto\Commerce\Domain\Order\ValueObject\OrderBuyer;
use Rebit\Share\Shared\Exception\HttpException;

/** Проверяет контакты покупателя по правилам оформления и приводит их к единому виду.
 * Канал чека принимается только из реально подключённых каналов, подтверждение состава обязательно.
 */
final readonly class BuyerPolicy
{
    /** @param list<string> $receiptChannels */
    public function accept(string $name, string $phone, string $email, string $comment, ?string $receiptChannel, bool $reviewed, array $receiptChannels): OrderBuyer
    {
        $name = trim($name);
        if (2 > mb_strlen($name) || 100 < mb_strlen($name)) {
            throw new HttpException('INVALID_BUYER_NAME', 422);
        }
        $digits = (string)preg_replace('/\D/', '', $phone);
        if (1 !== preg_match('/^[+\d\s().-]+$/D', $phone) || 10 > strlen($digits) || 15 < strlen($digits)) {
            throw new HttpException('INVALID_BUYER_PHONE', 422);
        }
        if (11 === strlen($digits) && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }
        $email = mb_strtolower(trim($email));
        if (254 < mb_strlen($email) || 1 !== preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/uD', $email)) {
            throw new HttpException('INVALID_BUYER_EMAIL', 422);
        }
        $comment = trim($comment);
        if (1000 < mb_strlen($comment)) {
            throw new HttpException('INVALID_BUYER_COMMENT', 422);
        }
        if (null !== $receiptChannel && !in_array($receiptChannel, $receiptChannels, true)) {
            throw new HttpException('RECEIPT_CHANNEL_UNAVAILABLE', 422);
        }
        if (!$reviewed) {
            throw new HttpException('REVIEW_REQUIRED', 422);
        }

        return new OrderBuyer($name, '+' . $digits, $email, $comment, $receiptChannel);
    }
}
