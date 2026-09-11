<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Catalog\Contract\ProductIdGeneratorInterface;
use Morefoto\Commerce\Application\Catalog\UseCase\CreateProductUseCase;
use Morefoto\Commerce\Application\Catalog\UseCase\ListProductsUseCase;
use Morefoto\Commerce\Application\Catalog\UseCase\UpdateProductUseCase;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;
use Morefoto\Commerce\Infrastructure\Adapter\BitrixCatalogTransaction;
use Morefoto\Commerce\Infrastructure\Adapter\ProductIdGenerator;

return [
    CatalogRepository::class => ['className' => CatalogRepository::class],
    CatalogTransactionInterface::class => [
        'constructor' => static fn(): CatalogTransactionInterface => new BitrixCatalogTransaction(),
    ],
    ProductIdGeneratorInterface::class => [
        'constructor' => static fn(): ProductIdGeneratorInterface => new ProductIdGenerator(),
    ],
    CreateProductUseCase::class => [
        'className' => CreateProductUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(CatalogRepository::class),
            ServiceLocator::getInstance()->get(CatalogTransactionInterface::class),
            ServiceLocator::getInstance()->get(ProductIdGeneratorInterface::class),
        ],
    ],
    UpdateProductUseCase::class => [
        'className' => UpdateProductUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(CatalogRepository::class),
            ServiceLocator::getInstance()->get(CatalogTransactionInterface::class),
        ],
    ],
    ListProductsUseCase::class => [
        'className' => ListProductsUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(CatalogRepository::class),
            ServiceLocator::getInstance()->get(CatalogTransactionInterface::class),
        ],
    ],
];
