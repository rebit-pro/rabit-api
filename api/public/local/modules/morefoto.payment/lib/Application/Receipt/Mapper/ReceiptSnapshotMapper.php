<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\Mapper;

use Morefoto\Payment\Domain\Receipt\Enum\PaymentModeEnum;
use Morefoto\Payment\Domain\Receipt\Enum\PaymentSubjectEnum;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptItem;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptSnapshot;

/**
 * Снимок чека в хранилище: в попытке — адрес и позиции, в чеке — только позиции.
 *
 * @phpstan-type StoredItem array{
 *     description: string,
 *     quantity: int,
 *     amount: int,
 *     print: bool,
 *     mode: string,
 *     subject: string,
 *     vatCode: int,
 * }
 */
final readonly class ReceiptSnapshotMapper
{
    public function encode(ReceiptSnapshot $snapshot): string
    {
        return json_encode(['email' => $snapshot->email, 'items' => $this->stored($snapshot->items)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function decode(string $json): ReceiptSnapshot
    {
        $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_string($data['email'] ?? null) || !is_array($data['items'] ?? null)) {
            throw new \UnexpectedValueException('Malformed receipt snapshot.');
        }

        return new ReceiptSnapshot($data['email'], $this->decodeItems($data['items']));
    }

    /** @param list<ReceiptItem> $items */
    public function encodeItems(array $items): string
    {
        return json_encode($this->stored($items), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @return non-empty-list<ReceiptItem> */
    public function decodeItemsJson(string $json): array
    {
        $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Malformed receipt items.');
        }

        return $this->decodeItems($data);
    }

    /**
     * @param list<ReceiptItem> $items
     *
     * @return list<StoredItem>
     */
    private function stored(array $items): array
    {
        $stored = [];
        foreach ($items as $item) {
            $stored[] = [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'amount' => $item->amount,
                'print' => $item->print,
                'mode' => $item->mode->value,
                'subject' => $item->subject->value,
                'vatCode' => $item->vatCode,
            ];
        }

        return $stored;
    }

    /**
     * @param array<mixed> $data
     *
     * @return non-empty-list<ReceiptItem>
     */
    private function decodeItems(array $data): array
    {
        $items = [];
        foreach ($data as $item) {
            if (!is_array($item) || !is_string($item['description'] ?? null) || !is_int($item['quantity'] ?? null) || !is_int($item['amount'] ?? null)
                || !is_bool($item['print'] ?? null) || !is_string($item['mode'] ?? null) || !is_string($item['subject'] ?? null) || !is_int($item['vatCode'] ?? null)) {
                throw new \UnexpectedValueException('Malformed receipt item.');
            }
            $items[] = new ReceiptItem(
                description: $item['description'],
                quantity: $item['quantity'],
                amount: $item['amount'],
                print: $item['print'],
                mode: PaymentModeEnum::from($item['mode']),
                subject: PaymentSubjectEnum::from($item['subject']),
                vatCode: $item['vatCode'],
            );
        }
        if ([] === $items) {
            throw new \UnexpectedValueException('A receipt has no items.');
        }

        return $items;
    }
}
