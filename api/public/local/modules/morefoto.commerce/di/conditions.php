<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Conditions\Service\ConditionsProducts;
use Morefoto\Commerce\Application\Conditions\UseCase\GetGlobalConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\GetGroupConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\SaveGlobalConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\SaveGroupConditionsUseCase;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;
use Morefoto\Commerce\Domain\Conditions\Repository\SalesConditionsRepository;

$services = [
    SalesConditionsRepository::class => ['className' => SalesConditionsRepository::class],
    ConditionsProducts::class => ['className' => ConditionsProducts::class],
];
$dependencies = [
    GetGlobalConditionsUseCase::class => [SalesConditionsRepository::class, CatalogRepository::class, CatalogTransactionInterface::class, ConditionsProducts::class],
    SaveGlobalConditionsUseCase::class => [SalesConditionsRepository::class, CatalogRepository::class, CatalogTransactionInterface::class, ConditionsProducts::class],
    GetGroupConditionsUseCase::class => [SalesConditionsRepository::class, CatalogRepository::class, CatalogTransactionInterface::class, ConditionsProducts::class],
    SaveGroupConditionsUseCase::class => [SalesConditionsRepository::class, CatalogRepository::class, CatalogTransactionInterface::class, ConditionsProducts::class],
];
foreach ($dependencies as $class => $arguments) {
    $services[$class] = [
        'className' => $class,
        'constructorParams' => static fn(): array => array_map(static fn(string $dependency): object => ServiceLocator::getInstance()->get($dependency), $arguments),
    ];
}

return $services;
