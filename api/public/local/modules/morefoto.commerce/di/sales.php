<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Commerce\Application\Sales\Contract\SalesAccessInterface;
use Morefoto\Commerce\Application\Sales\UseCase\GetGroupSalesUseCase;
use Morefoto\Commerce\Domain\Sales\Repository\GroupSalesRepository;
use Morefoto\Commerce\Infrastructure\Sales\SalesAccess;
use Morefoto\Commerce\Presentation\Controller\GroupSalesController;
use Morefoto\Commerce\Presentation\Sales\SalesResultMapper;
use Rebit\Share\Contracts\Access\StaffRequestAccessInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;

return [
    SalesAccessInterface::class => [
        'constructor' => static fn(): SalesAccessInterface => new SalesAccess(ServiceLocator::getInstance()->get(StaffRequestAccessInterface::class)),
    ],
    GroupSalesRepository::class => ['className' => GroupSalesRepository::class],
    SalesResultMapper::class => ['className' => SalesResultMapper::class],
    GetGroupSalesUseCase::class => [
        'className' => GetGroupSalesUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(SalesAccessInterface::class),
            ServiceLocator::getInstance()->get(GroupDirectoryInterface::class),
            ServiceLocator::getInstance()->get(GroupSalesRepository::class),
        ],
    ],
    GroupSalesController::class => [
        'className' => GroupSalesController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GetGroupSalesUseCase::class),
            ServiceLocator::getInstance()->get(SalesResultMapper::class),
        ],
    ],
];
