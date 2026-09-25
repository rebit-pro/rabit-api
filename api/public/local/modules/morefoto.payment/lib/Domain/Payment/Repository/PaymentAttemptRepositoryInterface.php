<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Payment\Repository;

use Morefoto\Payment\Domain\Payment\ValueObject\AttemptOutcome;
use Morefoto\Payment\Domain\Payment\ValueObject\PaymentSearchCriteria;

/**
 * Попытки оплаты; моменты — UTC `Y-m-d H:i:s`. Хранилище не делает HTTP и не меняет заказ.
 *
 * @phpstan-type NewAttemptRecord array{
 *     PUBLIC_ID: string,
 *     ORDER_ID: int,
 *     ORDER_PUBLIC_ID: string,
 *     ORDER_NUMBER: string,
 *     ORDER_VERSION: string,
 *     INSTITUTION_ID: int,
 *     INSTITUTION_NAME: string,
 *     GROUP_NAME: string,
 *     AMOUNT: int,
 *     PAYMENT_METHOD: string,
 *     PROVIDER: string,
 *     SHOP_ID: string,
 *     PRECEDING_ID: null|int,
 *     CLIENT_KEY_HASH: string,
 *     REQUEST_HASH: string,
 *     PROVIDER_KEY: string,
 *     NEXT_CHECK_AT: string,
 *     CREATED_AT: string,
 * }
 * @phpstan-type AttemptRecord array{
 *     ID: int,
 *     PUBLIC_ID: string,
 *     ORDER_ID: int,
 *     ORDER_PUBLIC_ID: string,
 *     ORDER_NUMBER: string,
 *     ORDER_VERSION: string,
 *     INSTITUTION_ID: int,
 *     INSTITUTION_NAME: string,
 *     GROUP_NAME: string,
 *     AMOUNT: int,
 *     CURRENCY: string,
 *     PAYMENT_METHOD: string,
 *     PROVIDER: string,
 *     SHOP_ID: string,
 *     STATUS: string,
 *     PRECEDING_ID: null|int,
 *     CLIENT_KEY_HASH: string,
 *     REQUEST_HASH: string,
 *     PROVIDER_KEY: string,
 *     PROVIDER_PAYMENT_ID: null|string,
 *     CONFIRMATION_URL: null|string,
 *     CANCEL_REASON: null|string,
 *     PAID_AT: null|string,
 *     INCOME_AMOUNT: null|int,
 *     LATE_PAYMENT: bool,
 *     CHECK_COUNT: int,
 *     NEXT_CHECK_AT: null|string,
 *     LAST_CHECK_AT: null|string,
 *     CREATED_AT: string,
 * }
 */
interface PaymentAttemptRepositoryInterface
{
    /**
     * Новая открытая попытка со статусом unknown; вторая открытая попытка заказа нарушает уникальность хранилища.
     *
     * @param NewAttemptRecord $record
     */
    public function insert(array $record): int;

    /** @return null|AttemptRecord */
    public function find(int $id): ?array;

    /** @return null|AttemptRecord */
    public function findPublic(string $publicId): ?array;

    /** @return null|AttemptRecord */
    public function findByClientKey(int $orderId, string $clientKeyHash): ?array;

    /** @return null|AttemptRecord */
    public function findByProviderPayment(string $provider, string $providerPaymentId): ?array;

    /** @return null|AttemptRecord последняя попытка заказа */
    public function latest(int $orderId): ?array;

    /** @return list<AttemptRecord> попытки заказа, новые первыми */
    public function forOrder(int $orderId): array;

    /**
     * Блокирует попытку до конца транзакции вызывающего.
     *
     * @return null|AttemptRecord
     */
    public function lock(int $id): ?array;

    /** Сохраняет итог сверки; закрытая попытка освобождает заказ для новой оплаты. */
    public function saveOutcome(int $id, AttemptOutcome $outcome): void;

    /** @return list<int> открытые попытки с наступившим сроком проверки, по возрастанию срока */
    public function due(string $now, int $limit): array;

    /** @return list<AttemptRecord> */
    public function page(PaymentSearchCriteria $criteria, int $limit, int $offset): array;

    public function count(PaymentSearchCriteria $criteria): int;
}
