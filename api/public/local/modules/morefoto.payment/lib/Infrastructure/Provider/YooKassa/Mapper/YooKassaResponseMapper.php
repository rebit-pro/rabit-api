<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Provider\YooKassa\Mapper;

use Morefoto\Payment\Application\Payment\Dto\ProviderPaymentOutputDto;
use Morefoto\Payment\Application\Payment\Exception\ProviderUnavailableException;

final readonly class YooKassaResponseMapper
{
    private const array STATUSES = ['pending', 'waiting_for_capture', 'succeeded', 'canceled'];

    /**
     * Объект платежа ЮKassa; ответ без id/статуса (например, «запрос ещё обрабатывается») — неизвестный исход.
     *
     * @param array<string, mixed> $response
     *
     * @throws ProviderUnavailableException
     */
    public function payment(array $response): ProviderPaymentOutputDto
    {
        $id = $response['id'] ?? null;
        $status = $response['status'] ?? null;
        $amount = $response['amount'] ?? null;
        if (!is_string($id) || '' === $id || 64 < strlen($id) || !in_array($status, self::STATUSES, true) || !is_array($amount)) {
            throw new ProviderUnavailableException('YooKassa returned no payment object.');
        }
        $recipient = is_array($response['recipient'] ?? null) ? $response['recipient'] : [];
        $metadata = is_array($response['metadata'] ?? null) ? $response['metadata'] : [];
        $confirmation = is_array($response['confirmation'] ?? null) ? $response['confirmation'] : [];
        $cancellation = is_array($response['cancellation_details'] ?? null) ? $response['cancellation_details'] : [];
        $income = $response['income_amount'] ?? null;

        return new ProviderPaymentOutputDto(
            id: $id,
            status: $status,
            amount: $this->minor($amount['value'] ?? null),
            currency: is_string($amount['currency'] ?? null) ? $amount['currency'] : '',
            shopId: is_scalar($recipient['account_id'] ?? null) ? (string)$recipient['account_id'] : '',
            attemptId: is_string($metadata['attemptId'] ?? null) ? $metadata['attemptId'] : null,
            confirmationUrl: $this->url($confirmation['confirmation_url'] ?? null),
            paidAt: $this->moment($response['captured_at'] ?? null),
            incomeAmount: is_array($income) && null !== ($income['value'] ?? null) ? $this->minor($income['value']) : null,
            cancelReason: is_string($cancellation['reason'] ?? null) ? mb_substr($cancellation['reason'], 0, 64) : null,
        );
    }

    /** «1050.00» → 105000 копеек без двоичной арифметики с плавающей точкой; иное значение — -1, оно не совпадёт с суммой попытки. */
    private function minor(mixed $value): int
    {
        if (!is_string($value) || 1 !== preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/D', $value, $parts)) {
            return -1;
        }

        return (int)$parts[1] * 100 + (int)str_pad($parts[2] ?? '0', 2, '0');
    }

    private function url(mixed $value): ?string
    {
        return is_string($value) && str_starts_with($value, 'https://') && 1024 >= strlen($value) ? $value : null;
    }

    private function moment(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        try {
            return new \DateTimeImmutable($value)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return null;
        }
    }
}
