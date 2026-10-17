<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Payment\Application\Payment\Contract\PaymentIdGeneratorInterface;
use Morefoto\Payment\Application\Payment\Contract\PaymentProviderInterface;
use Morefoto\Payment\Application\Payment\Contract\PaymentStaffAccessInterface;
use Morefoto\Payment\Application\Payment\Contract\PaymentTransactionInterface;
use Morefoto\Payment\Application\Payment\Mapper\PaymentOutputMapper;
use Morefoto\Payment\Application\Payment\Service\PaymentQuoteToken;
use Morefoto\Payment\Application\Payment\Service\PaymentReconciler;
use Morefoto\Payment\Application\Payment\Service\PaymentSettings;
use Morefoto\Payment\Application\Payment\UseCase\AcceptPaymentNotificationUseCase;
use Morefoto\Payment\Application\Payment\UseCase\GetPaymentAttemptUseCase;
use Morefoto\Payment\Application\Payment\UseCase\GetPaymentQuoteUseCase;
use Morefoto\Payment\Application\Payment\UseCase\GetPaymentUseCase;
use Morefoto\Payment\Application\Payment\UseCase\ListPaymentsUseCase;
use Morefoto\Payment\Application\Payment\UseCase\ReconcilePaymentsUseCase;
use Morefoto\Payment\Application\Payment\UseCase\StartPaymentAttemptUseCase;
use Morefoto\Payment\Domain\Payment\Enum\PaymentMethodEnum;
use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Repository\PaymentFactRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Repository\PaymentNotificationRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Service\PaymentAttemptPolicy;
use Morefoto\Payment\Infrastructure\Access\PaymentStaffAccess;
use Morefoto\Payment\Infrastructure\Config\PaymentConnectionConfig;
use Morefoto\Payment\Infrastructure\Database\BitrixPaymentAttemptRepository;
use Morefoto\Payment\Infrastructure\Database\BitrixPaymentFactRepository;
use Morefoto\Payment\Infrastructure\Database\BitrixPaymentNotificationRepository;
use Morefoto\Payment\Infrastructure\Database\BitrixPaymentTransaction;
use Morefoto\Payment\Infrastructure\Http\PaymentClientFactory;
use Morefoto\Payment\Infrastructure\Id\PaymentIdGenerator;
use Morefoto\Payment\Infrastructure\Provider\YooKassa\Mapper\YooKassaRequestMapper;
use Morefoto\Payment\Infrastructure\Provider\YooKassa\Mapper\YooKassaResponseMapper;
use Morefoto\Payment\Infrastructure\Provider\YooKassa\Provider\YooKassaClientProvider;
use Morefoto\Payment\Presentation\Command\ReconcilePaymentsCommand;
use Morefoto\Payment\Presentation\Controller\PaymentWebhookController;
use Morefoto\Payment\Presentation\Controller\PublicPaymentController;
use Morefoto\Payment\Presentation\Controller\StaffPaymentController;
use Morefoto\Payment\Infrastructure\Commerce\OrderPaymentRemoval;
use Rebit\Share\Contracts\Payment\OrderPaymentRemovalInterface;
use Morefoto\Payment\Presentation\Payment\PaymentInputMapper;
use Morefoto\Payment\Presentation\Payment\PaymentResultMapper;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Commerce\OrderPaymentInterface;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Facade\Log;
use Morefoto\Payment\Application\Receipt\Mapper\ReceiptOutputMapper;
use Morefoto\Payment\Application\Receipt\Mapper\ReceiptSnapshotMapper;
use Morefoto\Payment\Application\Receipt\Service\AttemptReceipt;
use Morefoto\Payment\Application\Receipt\Service\PrintHandoverView;
use Morefoto\Payment\Application\Receipt\Service\ReceiptReconciler;
use Morefoto\Payment\Application\Receipt\Service\ReceiptRegistrar;
use Morefoto\Payment\Application\Receipt\Service\ReceiptSettings;
use Morefoto\Payment\Application\Receipt\UseCase\GetOrderReceiptsUseCase;
use Morefoto\Payment\Application\Receipt\UseCase\GetPrintHandoversUseCase;
use Morefoto\Payment\Application\Receipt\UseCase\HandOverPrintUseCase;
use Morefoto\Payment\Domain\Receipt\Repository\PaymentReceiptRepositoryInterface;
use Morefoto\Payment\Domain\Receipt\Service\ReceiptComposer;
use Morefoto\Payment\Domain\Receipt\Service\ReceiptPolicy;
use Morefoto\Payment\Infrastructure\Config\ReceiptSettingsFactory;
use Morefoto\Payment\Infrastructure\Commerce\PaymentCapabilities;
use Morefoto\Payment\Infrastructure\Database\BitrixPaymentReceiptRepository;
use Morefoto\Payment\Presentation\Controller\StaffPrintHandoverController;
use Morefoto\Payment\Presentation\Receipt\ReceiptInputMapper;
use Morefoto\Payment\Presentation\Receipt\ReceiptResultMapper;
use Rebit\Share\Contracts\Commerce\GroupPrintOrdersInterface;
use Rebit\Share\Contracts\Commerce\OrderReceiptSourceInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Contracts\Payment\PaymentCapabilitiesInterface;

return [
    PaymentConnectionConfig::class => [
        // The secret key comes from the environment or the Docker secret loaded by runtime-env.php; never from documents.
        'constructor' => static fn(): PaymentConnectionConfig => new PaymentConnectionConfig(
            'https://api.yookassa.ru/v3/',
            trim((string)getenv('MOREFOTO_PAYMENT_YOOKASSA_SHOP_ID')),
            trim((string)getenv('MOREFOTO_PAYMENT_YOOKASSA_SECRET_KEY')),
        ),
    ],
    ReceiptSettings::class => [
        // G2-DEC-03: «Чеки от ЮKassa» and the item tax parameters; the tax system itself lives in the shop settings.
        'constructor' => static fn(): ReceiptSettings => new ReceiptSettingsFactory(Log::channel(LogChannelEnum::payment))->create(
            (string)getenv('MOREFOTO_PAYMENT_RECEIPTS'),
            (string)getenv('MOREFOTO_PAYMENT_RECEIPT_VAT_CODE'),
            (string)getenv('MOREFOTO_PAYMENT_RECEIPT_SUBJECT_PRINT'),
            (string)getenv('MOREFOTO_PAYMENT_RECEIPT_SUBJECT_DIGITAL'),
        ),
    ],
    PaymentSettings::class => [
        'constructor' => static function(): PaymentSettings {
            $connection = ServiceLocator::getInstance()->get(PaymentConnectionConfig::class);
            $receipts = ServiceLocator::getInstance()->get(ReceiptSettings::class);
            $returnBaseUrl = trim((string)getenv('MOREFOTO_PAYMENT_RETURN_BASE_URL'));
            $methods = [];
            foreach (explode(',', (string)getenv('MOREFOTO_PAYMENT_METHODS')) as $code) {
                $method = PaymentMethodEnum::tryFrom(trim($code));
                if (null !== $method && !in_array($method, $methods, true)) {
                    $methods[] = $method;
                }
            }

            return new PaymentSettings(
                '' !== $connection->shopId && '' !== $connection->secretKey && 1 === preg_match('#^https?://#', $returnBaseUrl) && !$receipts->misconfigured(),
                $methods,
                $returnBaseUrl,
                // YooKassa test shop keys start with test_, live ones with live_ (issue #120).
                str_starts_with($connection->secretKey, 'test_'),
            );
        },
    ],
    PaymentClientFactory::class => [
        'className' => PaymentClientFactory::class,
        'constructorParams' => static fn(): array => [
            Log::channel(LogChannelEnum::payment),
            ServiceLocator::getInstance()->get(PaymentConnectionConfig::class),
        ],
    ],
    YooKassaRequestMapper::class => ['className' => YooKassaRequestMapper::class],
    YooKassaResponseMapper::class => ['className' => YooKassaResponseMapper::class],
    PaymentProviderInterface::class => [
        'constructor' => static fn(): PaymentProviderInterface => new YooKassaClientProvider(
            ServiceLocator::getInstance()->get(PaymentClientFactory::class),
            ServiceLocator::getInstance()->get(YooKassaRequestMapper::class),
            ServiceLocator::getInstance()->get(YooKassaResponseMapper::class),
            ServiceLocator::getInstance()->get(PaymentConnectionConfig::class)->shopId,
        ),
    ],
    PaymentReceiptRepositoryInterface::class => [
        'constructor' => static fn(): PaymentReceiptRepositoryInterface => new BitrixPaymentReceiptRepository(),
    ],
    PaymentCapabilitiesInterface::class => [
        'constructor' => static fn(): PaymentCapabilitiesInterface => new PaymentCapabilities(
            ServiceLocator::getInstance()->get(PaymentSettings::class),
            ServiceLocator::getInstance()->get(ReceiptSettings::class),
        ),
    ],
    ReceiptComposer::class => ['className' => ReceiptComposer::class],
    ReceiptPolicy::class => ['className' => ReceiptPolicy::class],
    ReceiptSnapshotMapper::class => ['className' => ReceiptSnapshotMapper::class],
    ReceiptOutputMapper::class => [
        'className' => ReceiptOutputMapper::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ReceiptSnapshotMapper::class),
            ServiceLocator::getInstance()->get(PaymentOutputMapper::class),
        ],
    ],
    AttemptReceipt::class => [
        'className' => AttemptReceipt::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ReceiptSettings::class),
            ServiceLocator::getInstance()->get(OrderReceiptSourceInterface::class),
            ServiceLocator::getInstance()->get(ReceiptComposer::class),
            ServiceLocator::getInstance()->get(ReceiptSnapshotMapper::class),
        ],
    ],
    ReceiptRegistrar::class => [
        'className' => ReceiptRegistrar::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(PaymentReceiptRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PaymentIdGeneratorInterface::class),
            ServiceLocator::getInstance()->get(ReceiptSnapshotMapper::class),
            ServiceLocator::getInstance()->get(ReceiptComposer::class),
            ServiceLocator::getInstance()->get(ReceiptPolicy::class),
        ],
    ],
    ReceiptReconciler::class => [
        'className' => ReceiptReconciler::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(PaymentReceiptRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PaymentAttemptRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PaymentProviderInterface::class),
            ServiceLocator::getInstance()->get(PaymentTransactionInterface::class),
            ServiceLocator::getInstance()->get(ReceiptSnapshotMapper::class),
            ServiceLocator::getInstance()->get(ReceiptPolicy::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            Log::channel(LogChannelEnum::payment),
        ],
    ],
    PrintHandoverView::class => [
        'className' => PrintHandoverView::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GroupDirectoryInterface::class),
            ServiceLocator::getInstance()->get(GroupPrintOrdersInterface::class),
            ServiceLocator::getInstance()->get(PaymentReceiptRepositoryInterface::class),
            ServiceLocator::getInstance()->get(ReceiptSettings::class),
            ServiceLocator::getInstance()->get(PaymentOutputMapper::class),
        ],
    ],
    GetOrderReceiptsUseCase::class => [
        'className' => GetOrderReceiptsUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderPaymentInterface::class),
            ServiceLocator::getInstance()->get(PaymentReceiptRepositoryInterface::class),
            ServiceLocator::getInstance()->get(ReceiptSettings::class),
            ServiceLocator::getInstance()->get(ReceiptOutputMapper::class),
        ],
    ],
    GetPrintHandoversUseCase::class => [
        'className' => GetPrintHandoversUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(PaymentStaffAccessInterface::class),
            ServiceLocator::getInstance()->get(PrintHandoverView::class),
        ],
    ],
    HandOverPrintUseCase::class => [
        'className' => HandOverPrintUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(PaymentStaffAccessInterface::class),
            ServiceLocator::getInstance()->get(PrintHandoverView::class),
            ServiceLocator::getInstance()->get(ReceiptRegistrar::class),
            ServiceLocator::getInstance()->get(ReceiptSettings::class),
            ServiceLocator::getInstance()->get(PaymentTransactionInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            Log::channel(LogChannelEnum::payment),
        ],
    ],
    ReceiptInputMapper::class => ['className' => ReceiptInputMapper::class],
    ReceiptResultMapper::class => ['className' => ReceiptResultMapper::class],
    StaffPrintHandoverController::class => [
        'className' => StaffPrintHandoverController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GetPrintHandoversUseCase::class),
            ServiceLocator::getInstance()->get(HandOverPrintUseCase::class),
            ServiceLocator::getInstance()->get(ReceiptInputMapper::class),
            ServiceLocator::getInstance()->get(ReceiptResultMapper::class),
        ],
    ],
    PaymentAttemptRepositoryInterface::class => [
        'constructor' => static fn(): PaymentAttemptRepositoryInterface => new BitrixPaymentAttemptRepository(),
    ],
    PaymentFactRepositoryInterface::class => [
        'constructor' => static fn(): PaymentFactRepositoryInterface => new BitrixPaymentFactRepository(),
    ],
    PaymentNotificationRepositoryInterface::class => [
        'constructor' => static fn(): PaymentNotificationRepositoryInterface => new BitrixPaymentNotificationRepository(),
    ],
    PaymentTransactionInterface::class => [
        'constructor' => static fn(): PaymentTransactionInterface => new BitrixPaymentTransaction(),
    ],
    PaymentIdGeneratorInterface::class => [
        'constructor' => static fn(): PaymentIdGeneratorInterface => new PaymentIdGenerator(),
    ],
    PaymentStaffAccessInterface::class => [
        'constructor' => static fn(): PaymentStaffAccessInterface => new PaymentStaffAccess(
            ServiceLocator::getInstance()->get(InstitutionAccessInterface::class),
        ),
    ],
    PaymentAttemptPolicy::class => ['className' => PaymentAttemptPolicy::class],
    PaymentQuoteToken::class => ['className' => PaymentQuoteToken::class],
    PaymentOutputMapper::class => ['className' => PaymentOutputMapper::class],
    PaymentReconciler::class => [
        'className' => PaymentReconciler::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(PaymentAttemptRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PaymentFactRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PaymentProviderInterface::class),
            ServiceLocator::getInstance()->get(OrderPaymentInterface::class),
            ServiceLocator::getInstance()->get(PaymentTransactionInterface::class),
            ServiceLocator::getInstance()->get(PaymentAttemptPolicy::class),
            ServiceLocator::getInstance()->get(PaymentSettings::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            Log::channel(LogChannelEnum::payment),
            ServiceLocator::getInstance()->get(ReceiptRegistrar::class),
            ServiceLocator::getInstance()->get(ReceiptSnapshotMapper::class),
        ],
    ],
    GetPaymentQuoteUseCase::class => [
        'className' => GetPaymentQuoteUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderPaymentInterface::class),
            ServiceLocator::getInstance()->get(PaymentAttemptRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PaymentAttemptPolicy::class),
            ServiceLocator::getInstance()->get(PaymentQuoteToken::class),
            ServiceLocator::getInstance()->get(PaymentSettings::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    StartPaymentAttemptUseCase::class => [
        'className' => StartPaymentAttemptUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderPaymentInterface::class),
            ServiceLocator::getInstance()->get(PaymentAttemptRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PaymentReconciler::class),
            ServiceLocator::getInstance()->get(PaymentProviderInterface::class),
            ServiceLocator::getInstance()->get(PaymentTransactionInterface::class),
            ServiceLocator::getInstance()->get(PaymentAttemptPolicy::class),
            ServiceLocator::getInstance()->get(PaymentQuoteToken::class),
            ServiceLocator::getInstance()->get(PaymentSettings::class),
            ServiceLocator::getInstance()->get(PaymentIdGeneratorInterface::class),
            ServiceLocator::getInstance()->get(PaymentOutputMapper::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            ServiceLocator::getInstance()->get(AttemptReceipt::class),
        ],
    ],
    GetPaymentAttemptUseCase::class => [
        'className' => GetPaymentAttemptUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderPaymentInterface::class),
            ServiceLocator::getInstance()->get(PaymentAttemptRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PaymentReconciler::class),
            ServiceLocator::getInstance()->get(PaymentAttemptPolicy::class),
            ServiceLocator::getInstance()->get(PaymentOutputMapper::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    AcceptPaymentNotificationUseCase::class => [
        'className' => AcceptPaymentNotificationUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(PaymentProviderInterface::class),
            ServiceLocator::getInstance()->get(PaymentAttemptRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PaymentNotificationRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PaymentReconciler::class),
            ServiceLocator::getInstance()->get(PaymentAttemptPolicy::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    ReconcilePaymentsUseCase::class => [
        'className' => ReconcilePaymentsUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(PaymentAttemptRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PaymentReconciler::class),
            ServiceLocator::getInstance()->get(PaymentAttemptPolicy::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            Log::channel(LogChannelEnum::payment),
            ServiceLocator::getInstance()->get(PaymentReceiptRepositoryInterface::class),
            ServiceLocator::getInstance()->get(ReceiptReconciler::class),
        ],
    ],
    ListPaymentsUseCase::class => [
        'className' => ListPaymentsUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(PaymentStaffAccessInterface::class),
            ServiceLocator::getInstance()->get(PaymentAttemptRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PaymentOutputMapper::class),
            ServiceLocator::getInstance()->get(PaymentReceiptRepositoryInterface::class),
        ],
    ],
    GetPaymentUseCase::class => [
        'className' => GetPaymentUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(PaymentStaffAccessInterface::class),
            ServiceLocator::getInstance()->get(PaymentAttemptRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PaymentFactRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PaymentOutputMapper::class),
            ServiceLocator::getInstance()->get(PaymentReceiptRepositoryInterface::class),
            ServiceLocator::getInstance()->get(ReceiptOutputMapper::class),
        ],
    ],
    PaymentInputMapper::class => ['className' => PaymentInputMapper::class],
    PaymentResultMapper::class => [
        'className' => PaymentResultMapper::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ReceiptResultMapper::class),
        ],
    ],
    PublicPaymentController::class => [
        'className' => PublicPaymentController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GetPaymentQuoteUseCase::class),
            ServiceLocator::getInstance()->get(StartPaymentAttemptUseCase::class),
            ServiceLocator::getInstance()->get(GetPaymentAttemptUseCase::class),
            ServiceLocator::getInstance()->get(GetOrderReceiptsUseCase::class),
            ServiceLocator::getInstance()->get(PaymentInputMapper::class),
            ServiceLocator::getInstance()->get(PaymentResultMapper::class),
            ServiceLocator::getInstance()->get(ReceiptResultMapper::class),
        ],
    ],
    PaymentWebhookController::class => [
        'className' => PaymentWebhookController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(AcceptPaymentNotificationUseCase::class),
            ServiceLocator::getInstance()->get(PaymentInputMapper::class),
            ServiceLocator::getInstance()->get(PaymentResultMapper::class),
        ],
    ],
    StaffPaymentController::class => [
        'className' => StaffPaymentController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ListPaymentsUseCase::class),
            ServiceLocator::getInstance()->get(GetPaymentUseCase::class),
            ServiceLocator::getInstance()->get(PaymentInputMapper::class),
            ServiceLocator::getInstance()->get(PaymentResultMapper::class),
        ],
    ],
    ReconcilePaymentsCommand::class => [
        'className' => ReconcilePaymentsCommand::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ReconcilePaymentsUseCase::class),
        ],
    ],
    OrderPaymentRemovalInterface::class => ['className' => OrderPaymentRemoval::class],
];
