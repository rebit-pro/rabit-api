<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\Service;

use Morefoto\Commerce\Domain\Order\ValueObject\OrderBuyer;
use Rebit\Share\Shared\Exception\HttpException;

/** Проверяет контакт покупателя по правилам оформления E8: единственный обязательный контакт — email, имя и телефон
 * заказ не хранит. Канал чека принимается только из реально подключённых каналов, подтверждение состава обязательно.
 */
final readonly class BuyerPolicy
{
    /** @param list<string> $receiptChannels */
    public function accept(string $email, string $comment, ?string $receiptChannel, bool $reviewed, array $receiptChannels): OrderBuyer
    {
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

        return new OrderBuyer($email, $comment, $receiptChannel);
    }
}
