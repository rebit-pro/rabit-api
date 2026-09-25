<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Conditions\Service\ConditionsInputValidator;
use Morefoto\Commerce\Application\Conditions\Service\ConditionsProducts;
use Morefoto\Commerce\Application\Conditions\Service\PublishedPrices;
use Morefoto\Commerce\Application\Conditions\UseCase\GetGlobalConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\GetGroupConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\SaveGlobalConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\SaveGroupConditionsUseCase;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;
use Morefoto\Commerce\Domain\Conditions\Repository\SalesConditionsRepository;
use Morefoto\Commerce\Infrastructure\Handoff\GroupSalesReadiness;
use Rebit\Share\Contracts\Commerce\GroupSalesReadinessInterface;

$services = [
    SalesConditionsRepository::class => ['className' => SalesConditionsRepository::class],
    ConditionsProducts::class => ['className' => ConditionsProducts::class],
    ConditionsInputValidator::class => ['className' => ConditionsInputValidator::class],
    PublishedPrices::class => ['className' => PublishedPrices::class],
    GroupSalesReadinessInterface::class => [
        'constructor' => static fn(): GroupSalesReadinessInterface => new GroupSalesReadiness(
            ServiceLocator::getInstance()->get(SalesConditionsRepository::class),
            ServiceLocator::getInstance()->get(CatalogRepository::class),
            ServiceLocator::getInstance()->get(ConditionsProducts::class),
        ),
    ],
];
$dependencies = [
    GetGlobalConditionsUseCase::class => [SalesConditionsRepository::class, CatalogRepository::class, CatalogTransactionInterface::class, ConditionsProducts::class, PublishedPrices::class],
    SaveGlobalConditionsUseCase::class => [SalesConditionsRepository::class, CatalogRepository::class, CatalogTransactionInterface::class, ConditionsProducts::class, ConditionsInputValidator::class],
    GetGroupConditionsUseCase::class => [SalesConditionsRepository::class, CatalogRepository::class, CatalogTransactionInterface::class, ConditionsProducts::class, PublishedPrices::class],
    SaveGroupConditionsUseCase::class => [SalesConditionsRepository::class, CatalogRepository::class, CatalogTransactionInterface::class, ConditionsProducts::class, ConditionsInputValidator::class],
];
foreach ($dependencies as $class => $arguments) {
    $services[$class] = [
        'className' => $class,
        'constructorParams' => static fn(): array => array_map(static fn(string $dependency): object => ServiceLocator::getInstance()->get($dependency), $arguments),
    ];
}

return $services;
