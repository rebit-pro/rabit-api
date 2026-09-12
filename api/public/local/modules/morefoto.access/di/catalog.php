<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Access\Domain\Staff\Repository\CatalogAccessRepository;
use Morefoto\Access\Domain\Staff\Service\PermissionPolicy;
use Morefoto\Access\Infrastructure\Adapter\CatalogAccessGuard;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Contracts\Access\CatalogAccessGuardInterface;

return [
    CatalogAccessRepository::class => ['className' => CatalogAccessRepository::class],
    CatalogAccessGuardInterface::class => [
        'constructor' => static fn(): CatalogAccessGuardInterface => new CatalogAccessGuard(
            ServiceLocator::getInstance()->get(CatalogAccessRepository::class),
            ServiceLocator::getInstance()->get(IdentityGatewayInterface::class),
            ServiceLocator::getInstance()->get(TokenResolverInterface::class),
            ServiceLocator::getInstance()->get(PermissionPolicy::class),
        ),
    ],
];
