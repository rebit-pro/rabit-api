<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Commerce\Application\Conditions\UseCase\GetGroupConditionsUseCase;
use Morefoto\Commerce\Application\Storefront\Contract\QuoteTransactionInterface;
use Morefoto\Commerce\Application\Storefront\Service\StorefrontQuote;
use Morefoto\Commerce\Application\Storefront\UseCase\CreateQuoteUseCase;
use Morefoto\Commerce\Application\Storefront\UseCase\GetStorefrontCatalogUseCase;
use Morefoto\Commerce\Application\Storefront\UseCase\ValidateQuoteUseCase;
use Morefoto\Commerce\Domain\Storefront\Repository\QuoteRepository;
use Morefoto\Commerce\Infrastructure\Storefront\QuoteTransaction;
use Morefoto\Commerce\Presentation\Controller\StorefrontController;
use Morefoto\Commerce\Presentation\Storefront\StorefrontMapper;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Handoff\StaffEligibilityInterface;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;

return [
    QuoteTransactionInterface::class => [
        'constructor' => static fn(): QuoteTransactionInterface => new QuoteTransaction(),
    ],
    QuoteRepository::class => [
        'className' => QuoteRepository::class,
    ],
    StorefrontMapper::class => [
        'className' => StorefrontMapper::class,
    ],
    StorefrontQuote::class => [
        'className' => StorefrontQuote::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GalleryAccessInterface::class),
            ServiceLocator::getInstance()->get(GetGroupConditionsUseCase::class),
            ServiceLocator::getInstance()->get(StaffEligibilityInterface::class),
        ],
    ],
    GetStorefrontCatalogUseCase::class => [
        'className' => GetStorefrontCatalogUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GalleryAccessInterface::class),
            ServiceLocator::getInstance()->get(GetGroupConditionsUseCase::class),
        ],
    ],
    CreateQuoteUseCase::class => [
        'className' => CreateQuoteUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(StorefrontQuote::class),
            ServiceLocator::getInstance()->get(QuoteRepository::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            ServiceLocator::getInstance()->get(QuoteTransactionInterface::class),
        ],
    ],
    ValidateQuoteUseCase::class => [
        'className' => ValidateQuoteUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(StorefrontQuote::class),
            ServiceLocator::getInstance()->get(QuoteRepository::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            ServiceLocator::getInstance()->get(QuoteTransactionInterface::class),
        ],
    ],
    StorefrontController::class => [
        'className' => StorefrontController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GetStorefrontCatalogUseCase::class),
            ServiceLocator::getInstance()->get(CreateQuoteUseCase::class),
            ServiceLocator::getInstance()->get(StorefrontMapper::class),
        ],
    ],
];
