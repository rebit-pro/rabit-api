<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Catalog\Service\AuthorizedCatalog;
use Morefoto\Commerce\Application\Catalog\Service\CatalogPayloadHash;
use Morefoto\Commerce\Application\Catalog\UseCase\CreateProductUseCase;
use Morefoto\Commerce\Application\Catalog\UseCase\DeleteProductUseCase;
use Morefoto\Commerce\Application\Catalog\UseCase\ListProductsUseCase;
use Morefoto\Commerce\Application\Catalog\UseCase\UpdateProductUseCase;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogIdempotencyRepository;
use Morefoto\Commerce\Infrastructure\Adapter\CatalogTokenResolver;
use Morefoto\Commerce\Presentation\Catalog\ProductRemovalInputMapper;
use Morefoto\Commerce\Presentation\Controller\CatalogController;
use Morefoto\Commerce\Presentation\Controller\CatalogRemovalController;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;
use Morefoto\Commerce\Presentation\Mapper\CatalogResponseMapper;
use Morefoto\Commerce\Presentation\Request\CatalogRequestFactory;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Contracts\Access\CatalogAccessGuardInterface;

return [
    CatalogIdempotencyRepository::class => ['className' => CatalogIdempotencyRepository::class],
    CatalogPayloadHash::class => ['className' => CatalogPayloadHash::class],
    CatalogRequestFactory::class => ['className' => CatalogRequestFactory::class],
    CatalogResponseMapper::class => ['className' => CatalogResponseMapper::class],
    CatalogTokenResolver::class => [
        'className' => CatalogTokenResolver::class,
        'constructorParams' => static fn(): array => [ServiceLocator::getInstance()->get(TokenResolverInterface::class)],
    ],
    AuthorizedCatalog::class => [
        'className' => AuthorizedCatalog::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(CatalogTransactionInterface::class),
            ServiceLocator::getInstance()->get(CatalogAccessGuardInterface::class),
            ServiceLocator::getInstance()->get(CatalogIdempotencyRepository::class),
            ServiceLocator::getInstance()->get(CatalogPayloadHash::class),
            ServiceLocator::getInstance()->get(CreateProductUseCase::class),
            ServiceLocator::getInstance()->get(UpdateProductUseCase::class),
            ServiceLocator::getInstance()->get(ListProductsUseCase::class),
        ],
    ],
    CatalogController::class => [
        'className' => CatalogController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(AuthorizedCatalog::class),
            ServiceLocator::getInstance()->get(CatalogRequestFactory::class),
            ServiceLocator::getInstance()->get(CatalogResponseMapper::class),
            ServiceLocator::getInstance()->get(CatalogTokenResolver::class),
        ],
    ],
    DeleteProductUseCase::class => [
        'className' => DeleteProductUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(CatalogRepository::class),
            ServiceLocator::getInstance()->get(CatalogAccessGuardInterface::class),
            ServiceLocator::getInstance()->get(CatalogTransactionInterface::class),
        ],
    ],
    ProductRemovalInputMapper::class => ['className' => ProductRemovalInputMapper::class],
    CatalogRemovalController::class => [
        'className' => CatalogRemovalController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(DeleteProductUseCase::class),
            ServiceLocator::getInstance()->get(ProductRemovalInputMapper::class),
        ],
    ],
];
