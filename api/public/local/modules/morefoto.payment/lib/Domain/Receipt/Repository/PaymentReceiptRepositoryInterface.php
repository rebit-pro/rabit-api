<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Receipt\Repository;

use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptCheck;

/**
 * Кассовые чеки попыток оплаты; ITEMS — JSON позиций, моменты — UTC `Y-m-d H:i:s`. Хранилище не делает HTTP.
 *
 * @phpstan-type NewReceiptRecord array{
 *     PUBLIC_ID: string,
 *     KIND: string,
 *     ATTEMPT_ID: int,
 *     ORDER_ID: int,
 *     ORDER_PUBLIC_ID: string,
 *     PROVIDER: string,
 *     PROVIDER_PAYMENT_ID: string,
 *     PROVIDER_KEY: string,
 *     AMOUNT: int,
 *     ITEMS: string,
 *     STATUS: string,
 *     HANDED_AT: null|string,
 *     HANDED_BY: null|int,
 *     NEXT_CHECK_AT: string,
 *     CREATED_AT: string,
 * }
 * @phpstan-type ReceiptRecord array{
 *     ID: int,
 *     PUBLIC_ID: string,
 *     KIND: string,
 *     ATTEMPT_ID: int,
 *     ORDER_ID: int,
 *     ORDER_PUBLIC_ID: string,
 *     PROVIDER: string,
 *     PROVIDER_PAYMENT_ID: string,
 *     PROVIDER_KEY: string,
 *     PROVIDER_RECEIPT_ID: null|string,
 *     AMOUNT: int,
 *     ITEMS: string,
 *     STATUS: string,
 *     ERROR: null|string,
 *     FISCAL_DOCUMENT_NUMBER: null|string,
 *     FISCAL_STORAGE_NUMBER: null|string,
 *     FISCAL_ATTRIBUTE: null|string,
 *     REGISTERED_AT: null|string,
 *     HANDED_AT: null|string,
 *     HANDED_BY: null|int,
 *     SENT_AT: null|string,
 *     CHECK_COUNT: int,
 *     NEXT_CHECK_AT: null|string,
 *     LAST_CHECK_AT: null|string,
 *     CREATED_AT: string,
 * }
 */
interface PaymentReceiptRepositoryInterface
{
    /**
     * Один чек каждого вида на попытку: повтор того же вида ничего не меняет.
     *
     * @param NewReceiptRecord $record
     *
     * @return bool чек добавлен
     */
    public function insert(array $record): bool;

    /** @return null|ReceiptRecord */
    public function find(int $id): ?array;

    /**
     * Блокирует чек до конца транзакции вызывающего.
     *
     * @return null|ReceiptRecord
     */
    public function lock(int $id): ?array;

    /** @return list<ReceiptRecord> чеки заказа в порядке создания */
    public function forOrder(int $orderId): array;

    /**
     * @param list<string> $orderPublicIds
     *
     * @return list<ReceiptRecord>
     */
    public function forOrderPublicIds(array $orderPublicIds): array;

    /**
     * @param list<int> $attemptIds
     *
     * @return array<int, string> статус чека оплаты по внутреннему ID попытки
     */
    public function paymentStatuses(array $attemptIds): array;

    public function save(int $id, ReceiptCheck $check): void;

    /** Перед запросом создания: чек у провайдера, момент первого запроса сохраняется (срок ключа идемпотентности). */
    public function markSent(int $id, string $now): void;

    /** @return list<int> открытые чеки с наступившим сроком проверки, по возрастанию срока */
    public function due(string $now, int $limit): array;
}
