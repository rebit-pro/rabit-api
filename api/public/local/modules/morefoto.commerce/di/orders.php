<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Commerce\Application\Order\Contract\CheckoutKeySealInterface;
use Morefoto\Commerce\Application\Order\Contract\OrderStaffAccessInterface;
use Morefoto\Commerce\Application\Order\Contract\OrderTokenGeneratorInterface;
use Morefoto\Commerce\Application\Order\Contract\OrderTransactionInterface;
use Morefoto\Commerce\Application\Order\Mapper\OrderRecordMapper;
use Morefoto\Commerce\Application\Order\Mapper\OrderRowMapper;
use Morefoto\Commerce\Application\Order\Service\CheckoutAvailability;
use Morefoto\Commerce\Application\Order\Service\CheckoutReceipts;
use Morefoto\Commerce\Application\Order\Service\CheckoutRequestHash;
use Morefoto\Commerce\Application\Order\Service\OrderAccessKeys;
use Morefoto\Commerce\Application\Order\Service\OrderPeriods;
use Morefoto\Commerce\Application\Order\Service\OrderPlacement;
use Morefoto\Commerce\Application\Order\Service\OrderReader;
use Morefoto\Commerce\Application\Order\UseCase\CreateOrderUseCase;
use Morefoto\Commerce\Application\Order\UseCase\GetBuyerOrderUseCase;
use Morefoto\Commerce\Application\Order\UseCase\GetStaffOrderUseCase;
use Morefoto\Commerce\Application\Order\UseCase\SearchStaffOrdersUseCase;
use Morefoto\Commerce\Application\Storefront\UseCase\CreateQuoteUseCase;
use Morefoto\Commerce\Application\Storefront\UseCase\ValidateQuoteUseCase;
use Morefoto\Commerce\Domain\Order\Repository\OrderAccessKeyRepository;
use Morefoto\Commerce\Domain\Order\Repository\OrderCheckoutRepository;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Morefoto\Commerce\Domain\Order\Service\BuyerPolicy;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;
use Morefoto\Commerce\Infrastructure\Order\CheckoutKeySeal;
use Morefoto\Commerce\Infrastructure\Order\OrderStaffAccess;
use Morefoto\Commerce\Infrastructure\Order\OrderTokenGenerator;
use Morefoto\Commerce\Infrastructure\Order\OrderTransaction;
use Morefoto\Commerce\Infrastructure\Payment\OrderPayments;
use Morefoto\Commerce\Infrastructure\Transfer\ChildOrders;
use Morefoto\Commerce\Presentation\Controller\OrderController;
use Morefoto\Commerce\Presentation\Controller\StaffOrderController;
use Morefoto\Commerce\Presentation\Order\OrderInputMapper;
use Morefoto\Commerce\Presentation\Order\OrderResultMapper;
use Morefoto\Commerce\Presentation\Storefront\StorefrontMapper;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Commerce\ChildOrdersInterface;
use Rebit\Share\Contracts\Commerce\OrderPaymentInterface;
use Rebit\Share\Contracts\Media\ChildPhotosInterface;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Contracts\Organization\GroupReferenceInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;

return [
    CheckoutAvailability::class => [
        // Checkout stays disabled unless an isolated stand explicitly enables it; no charges are ever made by E5.
        'constructor' => static fn(): CheckoutAvailability => new CheckoutAvailability('1' === getenv('MOREFOTO_CHECKOUT_ENABLED')),
    ],
    OrderTransactionInterface::class => [
        'constructor' => static fn(): OrderTransactionInterface => new OrderTransaction(),
    ],
    ChildOrdersInterface::class => [
        'constructor' => static fn(): ChildOrdersInterface => new ChildOrders(ServiceLocator::getInstance()->get(OrderRepository::class)),
    ],
    OrderPaymentInterface::class => [
        'constructor' => static fn(): OrderPaymentInterface => new OrderPayments(
            ServiceLocator::getInstance()->get(OrderAccessKeyRepository::class),
            ServiceLocator::getInstance()->get(OrderRepository::class),
            ServiceLocator::getInstance()->get(GroupCalendarInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ),
    ],
    CheckoutKeySealInterface::class => [
        'constructor' => static fn(): CheckoutKeySealInterface => new CheckoutKeySeal(),
    ],
    OrderTokenGeneratorInterface::class => [
        'constructor' => static fn(): OrderTokenGeneratorInterface => new OrderTokenGenerator(),
    ],
    OrderStaffAccessInterface::class => [
        'constructor' => static fn(): OrderStaffAccessInterface => new OrderStaffAccess(
            ServiceLocator::getInstance()->get(InstitutionAccessInterface::class),
        ),
    ],
    OrderRepository::class => ['className' => OrderRepository::class],
    OrderAccessKeyRepository::class => ['className' => OrderAccessKeyRepository::class],
    OrderCheckoutRepository::class => ['className' => OrderCheckoutRepository::class],
    BuyerPolicy::class => ['className' => BuyerPolicy::class],
    OrderCalendarPolicy::class => ['className' => OrderCalendarPolicy::class],
    CheckoutRequestHash::class => ['className' => CheckoutRequestHash::class],
    OrderRecordMapper::class => ['className' => OrderRecordMapper::class],
    OrderResultMapper::class => ['className' => OrderResultMapper::class],
    OrderRowMapper::class => [
        'className' => OrderRowMapper::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderCalendarPolicy::class),
        ],
    ],
    OrderReader::class => [
        'className' => OrderReader::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderRepository::class),
            ServiceLocator::getInstance()->get(OrderRowMapper::class),
        ],
    ],
    OrderAccessKeys::class => [
        'className' => OrderAccessKeys::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderAccessKeyRepository::class),
            ServiceLocator::getInstance()->get(OrderTokenGeneratorInterface::class),
            ServiceLocator::getInstance()->get(OrderCalendarPolicy::class),
        ],
    ],
    OrderPeriods::class => [
        'className' => OrderPeriods::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GroupCalendarInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            ServiceLocator::getInstance()->get(OrderCalendarPolicy::class),
        ],
    ],
    CheckoutReceipts::class => [
        'className' => CheckoutReceipts::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderCheckoutRepository::class),
            ServiceLocator::getInstance()->get(CheckoutRequestHash::class),
            ServiceLocator::getInstance()->get(CheckoutKeySealInterface::class),
            ServiceLocator::getInstance()->get(OrderReader::class),
            ServiceLocator::getInstance()->get(OrderAccessKeyRepository::class),
            ServiceLocator::getInstance()->get(OrderCalendarPolicy::class),
        ],
    ],
    OrderPlacement::class => [
        'className' => OrderPlacement::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GroupReferenceInterface::class),
            ServiceLocator::getInstance()->get(MediaScopeInterface::class),
            ServiceLocator::getInstance()->get(OrderRepository::class),
            ServiceLocator::getInstance()->get(OrderRecordMapper::class),
            ServiceLocator::getInstance()->get(OrderAccessKeys::class),
            ServiceLocator::getInstance()->get(OrderReader::class),
            ServiceLocator::getInstance()->get(OrderTokenGeneratorInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    CreateOrderUseCase::class => [
        'className' => CreateOrderUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(CheckoutAvailability::class),
            ServiceLocator::getInstance()->get(GalleryAccessInterface::class),
            ServiceLocator::getInstance()->get(CheckoutReceipts::class),
            ServiceLocator::getInstance()->get(BuyerPolicy::class),
            ServiceLocator::getInstance()->get(ValidateQuoteUseCase::class),
            ServiceLocator::getInstance()->get(CreateQuoteUseCase::class),
            ServiceLocator::getInstance()->get(OrderPlacement::class),
            ServiceLocator::getInstance()->get(OrderTransactionInterface::class),
        ],
    ],
    GetBuyerOrderUseCase::class => [
        'className' => GetBuyerOrderUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderAccessKeyRepository::class),
            ServiceLocator::getInstance()->get(OrderReader::class),
            ServiceLocator::getInstance()->get(OrderPeriods::class),
            ServiceLocator::getInstance()->get(OrderCalendarPolicy::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    SearchStaffOrdersUseCase::class => [
        'className' => SearchStaffOrdersUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderStaffAccessInterface::class),
            ServiceLocator::getInstance()->get(OrderRepository::class),
            ServiceLocator::getInstance()->get(OrderRowMapper::class),
            ServiceLocator::getInstance()->get(OrderCalendarPolicy::class),
        ],
    ],
    GetStaffOrderUseCase::class => [
        'className' => GetStaffOrderUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderStaffAccessInterface::class),
            ServiceLocator::getInstance()->get(OrderRepository::class),
            ServiceLocator::getInstance()->get(OrderRowMapper::class),
            ServiceLocator::getInstance()->get(OrderPeriods::class),
            ServiceLocator::getInstance()->get(ChildPhotosInterface::class),
        ],
    ],
    OrderInputMapper::class => [
        'className' => OrderInputMapper::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(StorefrontMapper::class),
        ],
    ],
    OrderController::class => [
        'className' => OrderController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(CreateOrderUseCase::class),
            ServiceLocator::getInstance()->get(GetBuyerOrderUseCase::class),
            ServiceLocator::getInstance()->get(OrderInputMapper::class),
            ServiceLocator::getInstance()->get(OrderResultMapper::class),
        ],
    ],
    StaffOrderController::class => [
        'className' => StaffOrderController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(SearchStaffOrdersUseCase::class),
            ServiceLocator::getInstance()->get(GetStaffOrderUseCase::class),
            ServiceLocator::getInstance()->get(OrderInputMapper::class),
            ServiceLocator::getInstance()->get(OrderResultMapper::class),
        ],
    ],
];
