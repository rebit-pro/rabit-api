<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Service;

use Morefoto\Commerce\Application\Order\Contract\OrderTokenGeneratorInterface;
use Morefoto\Commerce\Application\Order\Dto\IssuedOrderKeyOutputDto;
use Morefoto\Commerce\Domain\Order\Repository\OrderAccessKeyRepository;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;

/** Ведёт жизненный цикл личного ключа заказа по D07: выдаёт ключ со сроком 30 календарных дней и отзывает действующий.
 * Хранилище получает только SHA-256 ключа; у заказа не бывает двух действующих ключей.
 */
final readonly class OrderAccessKeys
{
    private const array ISSUE_REASONS = ['checkout', 'recovery'];
    private const array REVOKE_REASONS = ['replaced', 'revoked'];

    public function __construct(
        private OrderAccessKeyRepository $keys,
        private OrderTokenGeneratorInterface $tokens,
        private OrderCalendarPolicy $calendar,
    ) {}

    public function issue(int $orderId, \DateTimeImmutable $now, string $reason, ?int $actorId = null): IssuedOrderKeyOutputDto
    {
        if (!in_array($reason, self::ISSUE_REASONS, true)) {
            throw new \InvalidArgumentException('Unknown order key issue reason.');
        }
        $key = $this->tokens->secret();
        $issuedAt = $now->setTimezone(new \DateTimeZone('UTC'));
        $expiresAt = $this->calendar->keyExpiresAt($issuedAt);
        $this->keys->insert($orderId, hash('sha256', $key), $issuedAt->format('Y-m-d H:i:s'), $expiresAt->format('Y-m-d H:i:s'), $reason, $actorId);

        return new IssuedOrderKeyOutputDto($key, $this->calendar->display($expiresAt));
    }

    public function revoke(int $orderId, \DateTimeImmutable $now, string $reason, ?int $actorId = null): bool
    {
        if (!in_array($reason, self::REVOKE_REASONS, true)) {
            throw new \InvalidArgumentException('Unknown order key revoke reason.');
        }

        return 0 < $this->keys->revokeActive($orderId, $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), $reason, $actorId);
    }
}
