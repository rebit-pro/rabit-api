<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit\Support;

use Morefoto\Payment\Application\Payment\Contract\PaymentProviderInterface;
use Morefoto\Payment\Application\Payment\Dto\CreateProviderPaymentInputDto;
use Morefoto\Payment\Application\Payment\Dto\CreateProviderReceiptInputDto;
use Morefoto\Payment\Application\Payment\Dto\ProviderPaymentOutputDto;
use Morefoto\Payment\Application\Payment\Dto\ProviderReceiptOutputDto;

/** Провайдер с очередью заранее заданных ответов; записывает вызовы для проверки ключей идемпотентности. */
final class FakeProvider implements PaymentProviderInterface
{
    /** @var list<CreateProviderPaymentInputDto> */
    public array $created = [];
    /** @var list<string> */
    public array $found = [];
    /** @var list<CreateProviderReceiptInputDto> */
    public array $createdReceipts = [];
    /** @var list<string> */
    public array $receiptQueries = [];
    /** @var list<ProviderPaymentOutputDto|\Throwable> */
    private array $answers = [];
    /** @var list<list<ProviderReceiptOutputDto>|ProviderReceiptOutputDto|\Throwable> */
    private array $receiptAnswers = [];

    /** @param list<ProviderReceiptOutputDto>|ProviderReceiptOutputDto|\Throwable ...$answers */
    public function answerReceipt(array|ProviderReceiptOutputDto|\Throwable ...$answers): void
    {
        array_push($this->receiptAnswers, ...$answers);
    }

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

    public function createReceipt(CreateProviderReceiptInputDto $input): ProviderReceiptOutputDto
    {
        $this->createdReceipts[] = $input;
        $answer = $this->nextReceipt();

        return is_array($answer) ? throw new \LogicException('A receipt object was expected.') : $answer;
    }

    public function findReceipt(string $providerReceiptId): ProviderReceiptOutputDto
    {
        $this->receiptQueries[] = $providerReceiptId;
        $answer = $this->nextReceipt();

        return is_array($answer) ? throw new \LogicException('A receipt object was expected.') : $answer;
    }

    public function paymentReceipts(string $providerPaymentId): array
    {
        $this->receiptQueries[] = 'payment:' . $providerPaymentId;
        $answer = $this->nextReceipt();

        return is_array($answer) ? $answer : [$answer];
    }

    public static function receipt(string $status, string $id = 'rt-1'): ProviderReceiptOutputDto
    {
        $registered = 'succeeded' === $status;

        return new ProviderReceiptOutputDto(
            id: $id,
            type: 'payment',
            status: $status,
            fiscalDocumentNumber: $registered ? '3986' : null,
            fiscalStorageNumber: $registered ? '9288000100115785' : null,
            fiscalAttribute: $registered ? '2617603921' : null,
            registeredAt: $registered ? '2026-09-25 12:02:00' : null,
        );
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

    /** @return list<ProviderReceiptOutputDto>|ProviderReceiptOutputDto */
    private function nextReceipt(): array|ProviderReceiptOutputDto
    {
        $answer = array_shift($this->receiptAnswers) ?? throw new \LogicException('No provider receipt answer queued.');
        if ($answer instanceof \Throwable) {
            throw $answer;
        }

        return $answer;
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
