<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit\Support;

use Morefoto\Payment\Application\Payment\Contract\PaymentProviderInterface;
use Morefoto\Payment\Application\Payment\Dto\CreateProviderPaymentInputDto;
use Morefoto\Payment\Application\Payment\Dto\ProviderPaymentOutputDto;

/** Провайдер с очередью заранее заданных ответов; записывает вызовы для проверки ключей идемпотентности. */
final class FakeProvider implements PaymentProviderInterface
{
    /** @var list<CreateProviderPaymentInputDto> */
    public array $created = [];
    /** @var list<string> */
    public array $found = [];
    /** @var list<ProviderPaymentOutputDto|\Throwable> */
    private array $answers = [];

    public function answer(ProviderPaymentOutputDto|\Throwable ...$answers): void
    {
        array_push($this->answers, ...$answers);
    }

    public function code(): string
    {
        return 'yookassa';
    }

    public function shopId(): string
    {
        return '123456';
    }

    public function create(CreateProviderPaymentInputDto $input): ProviderPaymentOutputDto
    {
        $this->created[] = $input;

        return $this->next();
    }

    public function find(string $providerPaymentId): ProviderPaymentOutputDto
    {
        $this->found[] = $providerPaymentId;

        return $this->next();
    }

    public static function payment(string $attemptId, string $status, int $amount = 105000, ?string $paidAt = null, string $shopId = '123456', string $id = 'yk-1'): ProviderPaymentOutputDto
    {
        return new ProviderPaymentOutputDto(
            id: $id,
            status: $status,
            amount: $amount,
            currency: 'RUB',
            shopId: $shopId,
            attemptId: $attemptId,
            confirmationUrl: 'pending' === $status ? 'https://yoomoney.ru/checkout/payments/v2/contract?orderId=' . $id : null,
            paidAt: $paidAt,
            incomeAmount: 'succeeded' === $status ? $amount - 3990 : null,
            cancelReason: 'canceled' === $status ? 'expired_on_confirmation' : null,
        );
    }

    private function next(): ProviderPaymentOutputDto
    {
        $answer = array_shift($this->answers) ?? throw new \LogicException('No provider answer queued.');
        if ($answer instanceof \Throwable) {
            throw $answer;
        }

        return $answer;
    }
}
