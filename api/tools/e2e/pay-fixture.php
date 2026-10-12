<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Payment\Application\Payment\Contract\PaymentIdGeneratorInterface;
use Morefoto\Payment\Application\Payment\Contract\PaymentProviderInterface;
use Morefoto\Payment\Application\Payment\Contract\PaymentTransactionInterface;
use Morefoto\Payment\Application\Payment\Dto\CreateProviderPaymentInputDto;
use Morefoto\Payment\Application\Payment\Dto\ProviderPaymentOutputDto;
use Morefoto\Payment\Application\Payment\Dto\StartPaymentInputDto;
use Morefoto\Payment\Application\Payment\Mapper\PaymentOutputMapper;
use Morefoto\Payment\Application\Payment\Service\PaymentQuoteToken;
use Morefoto\Payment\Application\Payment\Service\PaymentReconciler;
use Morefoto\Payment\Application\Payment\Service\PaymentSettings;
use Morefoto\Payment\Application\Payment\UseCase\GetPaymentQuoteUseCase;
use Morefoto\Payment\Application\Payment\UseCase\StartPaymentAttemptUseCase;
use Morefoto\Payment\Domain\Payment\Enum\ConfirmationSourceEnum;
use Morefoto\Payment\Domain\Payment\Enum\PaymentMethodEnum;
use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Repository\PaymentFactRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Service\PaymentAttemptPolicy;
use Morefoto\Payment\Domain\Payment\ValueObject\IdempotencyKey;
use Psr\Log\NullLogger;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\OrderPaymentInterface;

// J4-DEC-04: pays the order the browser placed through the G1 code (attempt, reconciliation, money fact, order status
// and key extension); only the provider is replaced, so the run needs no test shop. The attempts carry PROVIDER e2e-fixture.
if ('test' !== getenv('APP_ENV') || !is_file('/runtime/j4-pay-request.json')) {
    throw new RuntimeException('The payment fixture requires the disposable stand and a request of the browser.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
if (!Loader::includeModule('morefoto.payment')) {
    throw new RuntimeException('Cannot load isolated MoreFoto Payment module.');
}
$request = json_decode((string)file_get_contents('/runtime/j4-pay-request.json'), true, flags: JSON_THROW_ON_ERROR);
$orderKey = (string)$request['orderKey'];

// Creation answers like the provider page is waiting for the buyer; the status check reports the payment captured in full.
$provider = new class implements PaymentProviderInterface {
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
};
$services = ServiceLocator::getInstance();
$settings = new PaymentSettings(true, [PaymentMethodEnum::BANK_CARD], 'http://127.0.0.1');
$attempts = $services->get(PaymentAttemptRepositoryInterface::class);
$orders = $services->get(OrderPaymentInterface::class);
$clock = $services->get(ClockInterface::class);
$reconciler = new PaymentReconciler(
    $attempts,
    $services->get(PaymentFactRepositoryInterface::class),
    $provider,
    $orders,
    $services->get(PaymentTransactionInterface::class),
    $services->get(PaymentAttemptPolicy::class),
    $settings,
    $clock,
    new NullLogger(),
);
$quotes = new GetPaymentQuoteUseCase($orders, $attempts, $services->get(PaymentAttemptPolicy::class), $services->get(PaymentQuoteToken::class), $settings, $clock);
$quote = $quotes->execute($orderKey);
$started = new StartPaymentAttemptUseCase(
    $orders,
    $attempts,
    $reconciler,
    $provider,
    $services->get(PaymentTransactionInterface::class),
    $services->get(PaymentAttemptPolicy::class),
    $services->get(PaymentQuoteToken::class),
    $settings,
    $services->get(PaymentIdGeneratorInterface::class),
    $services->get(PaymentOutputMapper::class),
    $clock,
)->execute($orderKey, new IdempotencyKey(bin2hex(random_bytes(16))), new StartPaymentInputDto($quote->orderVersion, $quote->precedingAttemptId, $quote->quoteToken, PaymentMethodEnum::BANK_CARD->value));
// The buyer returns from the provider page: the server asks the provider and records the money.
$attempt = $attempts->findPublic($started->id) ?? throw new RuntimeException('The fixture attempt is missing.');
$status = $reconciler->reconcile($attempt['ID'], ConfirmationSourceEnum::RETURN);
if ('succeeded' !== $status?->value) {
    throw new RuntimeException('The fixture payment ended as ' . ($status?->value ?? 'missing'));
}
file_put_contents('/runtime/j4-paid.json', json_encode(['attemptId' => $started->id], JSON_THROW_ON_ERROR));
echo "J4 fixture payment succeeded\n";
