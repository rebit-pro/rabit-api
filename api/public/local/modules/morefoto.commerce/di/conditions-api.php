<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Conditions\Service\ConditionsPayloadHash;
use Morefoto\Commerce\Application\Conditions\UseCase\GetGlobalConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\GetGroupConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\ManageConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\SaveGlobalConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\SaveGroupConditionsUseCase;
use Morefoto\Commerce\Domain\Conditions\Repository\ConditionsIdempotencyRepository;
use Morefoto\Commerce\Presentation\Conditions\ConditionsInputMapper;
use Morefoto\Commerce\Presentation\Conditions\ConditionsResultMapper;
use Morefoto\Commerce\Presentation\Controller\ConditionsController;
use Rebit\Share\Contracts\Access\CatalogAccessGuardInterface;
use Rebit\Share\Contracts\Organization\GroupReferenceInterface;

return [
    ConditionsIdempotencyRepository::class => ['className' => ConditionsIdempotencyRepository::class],
    ConditionsPayloadHash::class => ['className' => ConditionsPayloadHash::class],
    ConditionsInputMapper::class => ['className' => ConditionsInputMapper::class],
    ConditionsResultMapper::class => ['className' => ConditionsResultMapper::class],
    ManageConditionsUseCase::class => [
        'className' => ManageConditionsUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(CatalogTransactionInterface::class),
            ServiceLocator::getInstance()->get(CatalogAccessGuardInterface::class),
            ServiceLocator::getInstance()->get(GroupReferenceInterface::class),
            ServiceLocator::getInstance()->get(ConditionsIdempotencyRepository::class),
            ServiceLocator::getInstance()->get(ConditionsPayloadHash::class),
            ServiceLocator::getInstance()->get(GetGlobalConditionsUseCase::class),
            ServiceLocator::getInstance()->get(SaveGlobalConditionsUseCase::class),
            ServiceLocator::getInstance()->get(GetGroupConditionsUseCase::class),
            ServiceLocator::getInstance()->get(SaveGroupConditionsUseCase::class),
        ],
    ],
    ConditionsController::class => [
        'className' => ConditionsController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ManageConditionsUseCase::class),
            ServiceLocator::getInstance()->get(ConditionsInputMapper::class),
            ServiceLocator::getInstance()->get(ConditionsResultMapper::class),
        ],
    ],
];
