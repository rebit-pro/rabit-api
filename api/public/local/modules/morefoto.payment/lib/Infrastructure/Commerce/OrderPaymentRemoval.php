<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Commerce;

use Bitrix\Main\Application;
use Rebit\Share\Contracts\Payment\OrderPaymentRemovalInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Money received or still possible keeps the order: a succeeded attempt means paid, an open one may still succeed at
 * YooKassa. Canceled attempts carry no money, so they go with the order (#92 DEC-02).
 */
final readonly class OrderPaymentRemoval implements OrderPaymentRemovalInterface
{
    public function remove(int $orderId): void
    {
        $connection = Application::getConnection();
        // Attempts change only under the order lock the caller holds; FOR UPDATE keeps the read consistent with the delete.
        $result = $connection->query("SELECT STATUS FROM mf_payment_attempt WHERE ORDER_ID={$orderId} FOR UPDATE");
        while (false !== ($row = $result->fetch())) {
            match ($row['STATUS']) {
                'succeeded' => throw new HttpException('ORDER_PAID', 409),
                'unknown', 'pending' => throw new HttpException('PAYMENT_IN_PROGRESS', 409),
                default => null,
            };
        }
        $connection->queryExecute("DELETE FROM mf_payment_attempt WHERE ORDER_ID={$orderId}");
    }
}
