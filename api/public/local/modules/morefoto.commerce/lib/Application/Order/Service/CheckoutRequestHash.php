<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Service;

use Morefoto\Commerce\Application\Order\Dto\CreateOrderInputDto;

/** Считает хеш нормализованного тела оформления, чтобы отличить повтор от другого запроса с тем же Idempotency-Key.
 * Порядок строк не влияет на хеш; расчёт, состав и поля покупателя сравниваются в присланном виде.
 */
final readonly class CheckoutRequestHash
{
    public function hash(CreateOrderInputDto $input): string
    {
        $lines = [];
        foreach ($input->lines as $line) {
            $lines[] = [$line->assignmentId, $line->productId, $line->quantity];
        }
        sort($lines);
        $buyer = $input->buyer;

        return hash('sha256', json_encode([
            'quoteToken' => $input->quoteToken,
            'lines' => $lines,
            'buyer' => [$buyer->name, $buyer->phone, $buyer->email, $buyer->comment, $buyer->receiptChannel, $buyer->reviewed],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
