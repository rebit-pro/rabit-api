<?php

declare(strict_types=1);

use Morefoto\Payment\Application\Payment\Contract\PaymentProviderInterface;
use Morefoto\Payment\Application\Payment\Dto\CreateProviderPaymentInputDto;
use Morefoto\Payment\Application\Payment\Dto\CreateProviderReceiptInputDto;
use Morefoto\Payment\Application\Payment\Dto\ProviderPaymentOutputDto;
use Morefoto\Payment\Application\Payment\Dto\ProviderReceiptOutputDto;

/**
 * J4-DEC-04, G2: the provider of the disposable stand. Creation answers like the provider page is waiting for the buyer, the
 * status check reports the payment captured in full; receipts are registered at once, and every closing receipt request is
 * kept in /runtime/g2-closing.json for the spec to compare with the receipt it expects.
 */
final class E2eFixturePaymentProvider implements PaymentProviderInterface
{
    public const string CLOSING_LOG = '/runtime/g2-closing.json';

    /** @var array<string, ProviderPaymentOutputDto> */
    private array $payments = [];

    public function code(): string
    {
        return 'e2e-fixture';
    }

    public function shopId(): string
    {
        return 'e2e-fixture';
    }

    public function create(CreateProviderPaymentInputDto $input): ProviderPaymentOutputDto
    {
        $id = 'e2e-' . $input->attemptId;

        return $this->payments[$id] = new ProviderPaymentOutputDto($id, 'pending', $input->amount, 'RUB', $this->shopId(), $input->attemptId, 'https://e2e.invalid/pay', null, null, null);
    }

    public function find(string $providerPaymentId): ProviderPaymentOutputDto
    {
        $payment = $this->payments[$providerPaymentId] ?? throw new RuntimeException('Unknown fixture payment.');

        return new ProviderPaymentOutputDto($payment->id, 'succeeded', $payment->amount, 'RUB', $this->shopId(), $payment->attemptId, null, null, $payment->amount, null);
    }

    public function createReceipt(CreateProviderReceiptInputDto $input): ProviderReceiptOutputDto
    {
        $log = is_file(self::CLOSING_LOG) ? json_decode((string)file_get_contents(self::CLOSING_LOG), true, flags: JSON_THROW_ON_ERROR) : [];
        $log[] = [
            'paymentId' => $input->providerPaymentId,
            'prepayment' => $input->prepayment,
            'key' => $input->idempotenceKey,
            'email' => $input->receipt->email,
            'items' => array_map(static fn($item): array => [$item->description, $item->quantity, $item->amount, $item->mode->value, $item->subject->value, $item->vatCode], $input->receipt->items),
        ];
        file_put_contents(self::CLOSING_LOG, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $this->registered('rt-closing-' . $input->providerPaymentId, '1002');
    }

    public function findReceipt(string $providerReceiptId): ProviderReceiptOutputDto
    {
        return $this->registered($providerReceiptId, '1002');
    }

    public function paymentReceipts(string $providerPaymentId): array
    {
        return [$this->registered('rt-' . $providerPaymentId, '1001')];
    }

    private function registered(string $id, string $document): ProviderReceiptOutputDto
    {
        return new ProviderReceiptOutputDto($id, 'payment', 'succeeded', $document, '9999078900001234', '3214567890', gmdate('Y-m-d H:i:s'));
    }
}
