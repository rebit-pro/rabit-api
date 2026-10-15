<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Files;

use Morefoto\Commerce\Application\Order\Service\CheckoutAvailability;
use Morefoto\Commerce\Domain\Order\Repository\OrderAccessKeyRepository;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\Dto\OrderEmailLinkOutputDto;
use Rebit\Share\Contracts\Commerce\Dto\OrderEmailOutputDto;
use Rebit\Share\Contracts\Commerce\OrderEmailLinkInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Ключ письма — HMAC публичного ID заказа на ключе, выведенном HKDF из серверного секрета: повторное письмо несёт ту же
 * ссылку и не закрывает страницу, открытую по прежнему письму; в базе только SHA-256 с типом `email`.
 */
final readonly class OrderEmailLinks implements OrderEmailLinkInterface
{
    private const string INFO = 'morefoto.commerce.order-email-key';
    private const int MIN_SECRET_LENGTH = 32;

    public function __construct(
        private OrderRepository $orders,
        private OrderAccessKeyRepository $keys,
        private OrderCalendarPolicy $calendar,
        private CheckoutAvailability $availability,
        private ClockInterface $clock,
        private string $secret,
    ) {}

    public function enabled(): bool
    {
        return $this->availability->orderEmail();
    }

    public function paidSince(\DateTimeImmutable $since, int $afterOrderId, int $limit): array
    {
        if (1 > $limit || 500 < $limit) {
            throw new \InvalidArgumentException('Invalid order email batch.');
        }
        $result = $this->orders->paidForEmail($since->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), max(0, $afterOrderId), $limit);
        $orders = [];
        while (false !== ($row = $result->fetch())) {
            $orders[] = $this->order($row);
        }

        return $orders;
    }

    public function find(int $orderId): ?OrderEmailOutputDto
    {
        $row = $this->orders->paidForEmailById($orderId)->fetch();

        return false === $row ? null : $this->order($row);
    }

    public function link(int $orderId): OrderEmailLinkOutputDto
    {
        $order = $this->find($orderId) ?? throw new HttpException('ORDER_NOT_FOUND', 404);
        if (self::MIN_SECRET_LENGTH > strlen($this->secret)) {
            throw new \RuntimeException('REBIT_ENCRYPTION_KEY is required to sign order email links.');
        }
        $key = hash_hmac('sha256', 'order-email|' . $order->publicId, hash_hkdf('sha256', $this->secret, 32, self::INFO));
        $utc = new \DateTimeZone('UTC');
        $expiresAt = $this->calendar->filesAvailableUntil(new \DateTimeImmutable($order->paidAt, $utc))->format('Y-m-d H:i:s');
        $hash = hash('sha256', $key);
        if ($this->keys->activeEmailKey($order->id) !== $hash) {
            $this->keys->replaceEmailKey($order->id, $hash, $this->clock->now()->setTimezone($utc)->format('Y-m-d H:i:s'), $expiresAt);
        }

        return new OrderEmailLinkOutputDto($key, $expiresAt);
    }

    /** @param array<string, mixed> $row */
    private function order(array $row): OrderEmailOutputDto
    {
        return new OrderEmailOutputDto(
            id: (int)$row['ID'],
            publicId: (string)$row['PUBLIC_ID'],
            number: (string)$row['NUMBER'],
            email: (string)$row['BUYER_EMAIL'],
            paidAt: (string)$row['PAID_AT'],
            recordedAt: (string)$row['PAID_RECORDED_AT'],
        );
    }
}
