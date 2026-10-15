<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce;

use Rebit\Share\Contracts\Commerce\Dto\OrderEmailLinkOutputDto;
use Rebit\Share\Contracts\Commerce\Dto\OrderEmailOutputDto;

/**
 * J2: письмо покупателю со ссылкой на оплаченный заказ. Commerce владеет флагом отправки, email заказа и ключом письма;
 * отправку ведёт вызывающий модуль.
 */
interface OrderEmailLinkInterface
{
    /** J2-DEC-05: отправка включена флагом окружения; выключенная копит письма, не отправляя их. */
    public function enabled(): bool;

    /**
     * Заказы, оплату которых сервер подтвердил после курсора (момент, ID заказа), в порядке подтверждения. Поздняя оплата
     * (G1) и заказы, оплаченные до выкладки J2 (момента подтверждения нет, J2-DEC-02), не входят.
     *
     * @return list<OrderEmailOutputDto>
     */
    public function paidSince(\DateTimeImmutable $since, int $afterOrderId, int $limit): array;

    /** Оплаченный заказ по внутреннему ID для письма; null — заказа нет или он не оплачен. */
    public function find(int $orderId): ?OrderEmailOutputDto;

    /**
     * Ключ письма оплаченного заказа (J2-DEC-01): отдельный от ключа покупателя, действует до конца месяца файлов. Ключ
     * выводится из серверного секрета, поэтому повторный вызов возвращает ту же ссылку и не отзывает уже отправленную.
     * В хранилище — только SHA-256.
     */
    public function link(int $orderId): OrderEmailLinkOutputDto;
}
