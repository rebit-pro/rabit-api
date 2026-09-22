<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Media\Application\Photo\Contract\MediaTransactionInterface;
use Morefoto\Media\Application\Transfer\Service\ChildTransfers;
use Morefoto\Media\Application\Transfer\UseCase\TransferChildUseCase;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Transfer\Repository\ChildTransferRepository;
use Morefoto\Media\Domain\Transfer\Service\ChildTransferPolicy;
use Morefoto\Media\Presentation\Controller\ChildTransferController;
use Morefoto\Media\Presentation\Transfer\ChildTransferInputMapper;
use Morefoto\Media\Presentation\Transfer\ChildTransferResultMapper;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Commerce\ChildOrdersInterface;
use Rebit\Share\Contracts\Media\ChildTransferInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;

// Commerce includes Media; the orders contract is resolved lazily after init.php loaded both modules.
$services = [
    ChildTransferRepository::class => ['className' => ChildTransferRepository::class],
    ChildTransferPolicy::class => ['className' => ChildTransferPolicy::class],
    ChildTransferInputMapper::class => ['className' => ChildTransferInputMapper::class],
    ChildTransferResultMapper::class => ['className' => ChildTransferResultMapper::class],
    ChildTransferInterface::class => [
        'constructor' => static fn(): ChildTransferInterface => ServiceLocator::getInstance()->get(ChildTransfers::class),
    ],
];
$dependencies = [
    ChildTransfers::class => [MediaMutationRepository::class, ChildTransferRepository::class, ChildTransferPolicy::class],
    TransferChildUseCase::class => [
        MediaTransactionInterface::class,
        MediaMutationRepository::class,
        ChildTransferRepository::class,
        ChildTransfers::class,
        ChildTransferPolicy::class,
        MediaScopeInterface::class,
        AccessGuardInterface::class,
        ChildOrdersInterface::class,
    ],
    ChildTransferController::class => [TransferChildUseCase::class, ChildTransferInputMapper::class, ChildTransferResultMapper::class],
];
foreach ($dependencies as $class => $arguments) {
    $services[$class] = [
        'className' => $class,
        'constructorParams' => static fn(): array => array_map(static fn(string $dependency): object => ServiceLocator::getInstance()->get($dependency), $arguments),
    ];
}

return $services;
