<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce;

use Rebit\Share\Contracts\Commerce\Dto\PaidPrintLineOutputDto;

/** Оплаченная печать группы из неизменяемых снимков заказов для архива печати L1; заказы через контракт не меняются. */
interface GroupPrintOrdersInterface
{
    /**
     * Печатные строки заказов группы, оплата которых подтверждена до срока печати: paid, PAID_AT раньше срока и без
     * отметки поздней оплаты. Цифровые строки, комплекты, неоплаченные и ожидающие заказы не входят.
     *
     * @param string             $groupId  публичный ID группы Organization
     * @param \DateTimeImmutable $deadline срок приёма печати группы
     *
     * @return list<PaidPrintLineOutputDto> в порядке заказа и номера строки
     */
    public function paidPrintLines(string $groupId, \DateTimeImmutable $deadline): array;

    /** Заказы группы с печатью, оплата которых ещё ждёт подтверждения провайдера (открытая попытка G1). */
    public function waitingPrintOrders(string $groupId): int;
}
