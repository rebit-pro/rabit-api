<?php

declare(strict_types=1);

namespace Morefoto\Access\Infrastructure\Adapter;

use Morefoto\Access\Domain\Staff\Entity\StaffProfile;
use Morefoto\Access\Domain\Staff\Enum\PermissionEnum;
use Morefoto\Access\Domain\Staff\Repository\CatalogAccessRepository;
use Morefoto\Access\Domain\Staff\Service\PermissionPolicy;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Contracts\Access\CatalogAccessException;
use Rebit\Share\Contracts\Access\CatalogAccessGuardInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class CatalogAccessGuard implements CatalogAccessGuardInterface
{
    public function __construct(
        private CatalogAccessRepository $profiles,
        private IdentityGatewayInterface $identities,
        private TokenResolverInterface $tokens,
        private PermissionPolicy $policy,
    ) {}

    public function lockOrganizer(int $actorId, string $token): void
    {
        try {
            if (0 >= $actorId || '' === $token) {
                throw new CatalogAccessException('Unauthorized', 401);
            }
            $profile = StaffProfile::fromRow($this->profiles->lockProfile($actorId)->fetch());
            if (null === $this->identities->lockActive($actorId) || $actorId !== $this->tokens->resolveUserId($token)) {
                throw new CatalogAccessException('Unauthorized', 401);
            }
            if (null === $profile || !$this->policy->allows($profile, PermissionEnum::CATALOG_MANAGE)) {
                throw new CatalogAccessException('Catalogue access is forbidden.', 403);
            }
        } catch (CatalogAccessException $exception) {
            throw $exception;
        } catch (HttpException $exception) {
            throw new CatalogAccessException('Unauthorized', 401, $exception);
        } catch (\Throwable $exception) {
            throw new CatalogAccessException('Access service is unavailable.', 503, $exception);
        }
    }
}
