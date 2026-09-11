<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Bootstrap\UseCase;

use Morefoto\Access\Domain\Staff\Entity\StaffProfile;
use Morefoto\Access\Domain\Staff\Enum\RoleEnum;
use Morefoto\Access\Domain\Staff\Repository\AccessStateRepository;
use Morefoto\Access\Domain\Staff\Repository\StaffProfileRepository;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;

final readonly class BootstrapOrganizerUseCase
{
    public function __construct(
        private AccessStateRepository $state,
        private StaffProfileRepository $profiles,
        private IdentityGatewayInterface $identities,
    ) {}

    public function execute(int $userId): bool
    {
        if (0 >= $userId) {
            throw new \InvalidArgumentException('A positive user ID is required.');
        }

        return $this->state->withLock(function() use ($userId): bool {
            $profile = StaffProfile::fromRow($this->profiles->findByUserId($userId)->fetch());
            if (null !== $profile && $profile->isEnabled() && RoleEnum::ORGANIZER === $profile->role) {
                return false;
            }
            if ($this->profiles->hasProfiles()) {
                throw new \DomainException('Bootstrap requires an empty staff directory. Use staff administration for further changes.');
            }
            if (null === $this->identities->lockActive($userId)) {
                throw new \DomainException('An existing active, non-pending Auth identity is required.');
            }
            $this->profiles->addFirstOrganizer($userId);
            $this->identities->revokeSessions($userId);

            return true;
        });
    }
}
