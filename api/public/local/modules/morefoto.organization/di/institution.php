<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionRepository;
use Morefoto\Organization\Application\Institution\UseCase\CreateInstitutionUseCase;
use Morefoto\Organization\Application\Institution\UseCase\UpdateInstitutionUseCase;
use Morefoto\Organization\Application\Institution\UseCase\GetInstitutionUseCase;
use Morefoto\Organization\Application\Institution\UseCase\ListInstitutionsUseCase;
use Morefoto\Organization\Application\Institution\Service\PrintInstitutionDirectory;
use Rebit\Share\Contracts\Organization\PrintInstitutionDirectoryInterface;

$services = [InstitutionRepository::class => ['className' => InstitutionRepository::class]];
$services[PrintInstitutionDirectoryInterface::class] = [
    'constructor' => static fn(): PrintInstitutionDirectoryInterface => new PrintInstitutionDirectory(
        ServiceLocator::getInstance()->get(InstitutionRepository::class),
    ),
];
foreach ([CreateInstitutionUseCase::class, UpdateInstitutionUseCase::class, GetInstitutionUseCase::class, ListInstitutionsUseCase::class] as $class) {
    $services[$class] = [
        'className' => $class,
        'constructorParams' => static fn(): array => [ServiceLocator::getInstance()->get(InstitutionRepository::class)],
    ];
}

return $services;
