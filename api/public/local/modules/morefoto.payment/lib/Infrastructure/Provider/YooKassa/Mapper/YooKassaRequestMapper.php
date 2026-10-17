<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Provider\YooKassa\Mapper;

use Morefoto\Payment\Application\Payment\Dto\CreateProviderPaymentInputDto;
use Morefoto\Payment\Application\Payment\Dto\CreateProviderReceiptInputDto;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptSnapshot;

final readonly class YooKassaRequestMapper
{
    private const int DESCRIPTION_LIMIT = 128;
    /** Мера количества обязательна для «Чеков от ЮKassa» (ФФД 1.2); фотографии и отпечатки считаются штуками. */
    private const string MEASURE = 'piece';

    /**
     * Одностадийный платёж выбранным способом с переходом на страницу ЮKassa (G1-DEC-03).
     *
     * @return array{
     *     amount: array{value: string, currency: string},
     *     capture: bool,
     *     payment_method_data: array{type: string},
     *     confirmation: array{type: string, return_url: string},
     *     description: string,
     *     metadata: array{attemptId: string, orderId: string},
     *     receipt?: array{customer: array{email: string}, items: list<array<string, mixed>>},
     * }
     */
    public function createPayment(CreateProviderPaymentInputDto $input): array
    {
        $request = [
            'amount' => ['value' => $this->money($input->amount), 'currency' => 'RUB'],
            'capture' => true,
            'payment_method_data' => ['type' => $input->paymentMethod],
            'confirmation' => ['type' => 'redirect', 'return_url' => $input->returnUrl],
            'description' => mb_substr($input->description, 0, self::DESCRIPTION_LIMIT),
            'metadata' => ['attemptId' => $input->attemptId, 'orderId' => $input->orderId],
        ];
        if (null !== $input->receipt) {
            // The tax system is not sent: for «Чеки от ЮKassa» it comes from the shop settings (G2-D09).
            $request['receipt'] = $this->receipt($input->receipt);
        }

        return $request;
    }

    /**
     * Чек зачёта предоплаты (G2-DEC-02): тип «приход», позиции полным расчётом, settlements — внесённая предоплата.
     *
     * @return array{
     *     type: string,
     *     payment_id: string,
     *     send: bool,
     *     customer: array{email: string},
     *     items: list<array<string, mixed>>,
     *     settlements: list<array{type: string, amount: array{value: string, currency: string}}>,
     * }
     */
    public function closingReceipt(CreateProviderReceiptInputDto $input): array
    {
        return [
            'type' => 'payment',
            'payment_id' => $input->providerPaymentId,
            'send' => true,
            ...$this->receipt($input->receipt),
            'settlements' => [['type' => 'prepayment', 'amount' => ['value' => $this->money($input->prepayment), 'currency' => 'RUB']]],
        ];
    }

    /** @return array{customer: array{email: string}, items: list<array<string, mixed>>} */
    private function receipt(ReceiptSnapshot $receipt): array
    {
        $items = [];
        foreach ($receipt->items as $item) {
            $items[] = [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'amount' => ['value' => $this->money($item->amount), 'currency' => 'RUB'],
                'vat_code' => $item->vatCode,
                'payment_mode' => $item->mode->value,
                'payment_subject' => $item->subject->value,
                'measure' => self::MEASURE,
            ];
        }

        return ['customer' => ['email' => $receipt->email], 'items' => $items];
    }

    private function money(int $minor): string
    {
        return intdiv($minor, 100) . '.' . str_pad((string)($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
