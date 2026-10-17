<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Receipt\Service;

use Morefoto\Payment\Domain\Receipt\Enum\PaymentModeEnum;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptItem;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptLine;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptRules;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptSnapshot;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Составляет кассовые чеки заказа по схеме владельца (G2-D09): печать оплачивается полной предоплатой и закрывается
 * полным расчётом при выдаче, электронные фото и комплект — полный расчёт сразу. Позиции группируются по виду, товару
 * и цене, бесплатные строки (подарок) в чек не входят, а сумма позиций обязана совпасть с суммой платежа.
 */
final readonly class ReceiptComposer
{
    /** Предел позиций «Чеков от ЮKassa». */
    public const int MAX_ITEMS = 80;
    private const int DESCRIPTION_LIMIT = 128;
    /** Название позиции без названия товара: провайдер требует от 1 до 128 символов. */
    private const string FALLBACK_DESCRIPTION = 'Фотография';

    /**
     * Чек оплаты из строк снимка заказа.
     *
     * @param list<ReceiptLine> $lines
     *
     * @throws HttpException RECEIPT_TOO_LARGE, RECEIPT_MISMATCH 409
     */
    public function payment(string $email, array $lines, int $total, ReceiptRules $rules): ReceiptSnapshot
    {
        /** @var array<string, ReceiptItem> $items */
        $items = [];
        foreach ($lines as $line) {
            if (0 >= $line->total) {
                continue;
            }
            $description = mb_substr(trim($line->name), 0, self::DESCRIPTION_LIMIT);
            $description = '' === $description ? self::FALLBACK_DESCRIPTION : $description;
            $key = ($line->print ? 'p' : 'd') . "\n" . $line->unitPrice . "\n" . $description;
            $items[$key] = new ReceiptItem(
                description: $description,
                quantity: ($items[$key]->quantity ?? 0) + $line->quantity,
                amount: $line->unitPrice,
                print: $line->print,
                mode: $line->print ? PaymentModeEnum::FULL_PREPAYMENT : PaymentModeEnum::FULL_PAYMENT,
                subject: $line->print ? $rules->printSubject : $rules->digitalSubject,
                vatCode: $rules->vatCode,
            );
        }
        if (self::MAX_ITEMS < count($items)) {
            throw new HttpException('RECEIPT_TOO_LARGE', 409);
        }
        if ([] === $items) {
            throw new HttpException('RECEIPT_MISMATCH', 409);
        }
        $snapshot = new ReceiptSnapshot($email, array_values($items));
        if ($snapshot->total() !== $total) {
            throw new HttpException('RECEIPT_MISMATCH', 409);
        }

        return $snapshot;
    }

    /**
     * Позиции закрывающего чека: печатные позиции чека оплаты полным расчётом; пустой список — печати в заказе нет.
     *
     * @param list<ReceiptItem> $paymentItems
     *
     * @return list<ReceiptItem>
     */
    public function closing(array $paymentItems): array
    {
        $items = [];
        foreach ($paymentItems as $item) {
            if ($item->print) {
                $items[] = new ReceiptItem($item->description, $item->quantity, $item->amount, true, PaymentModeEnum::FULL_PAYMENT, $item->subject, $item->vatCode);
            }
        }

        return $items;
    }
}
