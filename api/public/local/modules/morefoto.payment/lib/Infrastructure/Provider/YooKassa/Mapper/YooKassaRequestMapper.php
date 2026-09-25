<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Provider\YooKassa\Mapper;

use Morefoto\Payment\Application\Payment\Dto\CreateProviderPaymentInputDto;

final readonly class YooKassaRequestMapper
{
    private const int DESCRIPTION_LIMIT = 128;

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
     * }
     */
    public function createPayment(CreateProviderPaymentInputDto $input): array
    {
        return [
            'amount' => ['value' => $this->money($input->amount), 'currency' => 'RUB'],
            'capture' => true,
            'payment_method_data' => ['type' => $input->paymentMethod],
            'confirmation' => ['type' => 'redirect', 'return_url' => $input->returnUrl],
            'description' => mb_substr($input->description, 0, self::DESCRIPTION_LIMIT),
            'metadata' => ['attemptId' => $input->attemptId, 'orderId' => $input->orderId],
        ];
    }

    private function money(int $minor): string
    {
        return intdiv($minor, 100) . '.' . str_pad((string)($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
